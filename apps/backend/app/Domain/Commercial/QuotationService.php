<?php

namespace App\Domain\Commercial;

use App\Domain\Tenancy\Audit;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationActivity;
use App\Models\QuotationItem;
use App\Models\QuotationStatusHistory;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QuotationService
{
    private const TRANSITIONS = [
        'draft' => ['pending_review', 'cancelled'],
        'pending_review' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['sent', 'cancelled'],
        'sent' => ['accepted', 'rejected', 'expired'],
        'accepted' => [], 'rejected' => [], 'expired' => [], 'cancelled' => [],
    ];

    public function create(Tenant $tenant, User $actor, array $data): Quotation
    {
        return DB::transaction(function () use ($tenant, $actor, $data): Quotation {
            $this->ensureActor($tenant, $actor);
            $customer = Customer::forTenant($tenant)->whereKey($data['customer_id'])->where('status', 'active')->firstOrFail();
            $list = PriceList::forTenant($tenant)->whereKey($data['price_list_id'])->where('is_active', true)->firstOrFail();
            abort_if($list->type === 'internal' || ($list->type !== 'public' && ! in_array(request()->attributes->get('activeMembership')?->role, ['owner', 'admin'], true)), 403);
            $address = isset($data['address_id']) ? CustomerAddress::forTenant($tenant)->where('customer_id', $customer->id)->findOrFail($data['address_id']) : CustomerAddress::forTenant($tenant)->where('customer_id', $customer->id)->where('is_primary', true)->first();
            $quote = Quotation::create([
                'tenant_id' => $tenant->id,
                'quotation_number' => CommercialNumber::next($tenant->id, 'quotation', 'PRE-'),
                'customer_id' => $customer->id,
                'seller_id' => $actor->id,
                'price_list_id' => $list->id,
                'status' => 'draft',
                'currency' => 'ARS',
                'sale_modality' => 'cash',
                'customer_snapshot' => $this->customerSnapshot($customer),
                'address_snapshot' => $address?->only(['label', 'street', 'street_number', 'floor', 'unit', 'city', 'province', 'postal_code', 'country']),
            ]);
            QuotationStatusHistory::create(['tenant_id' => $tenant->id, 'quotation_id' => $quote->id, 'actor_user_id' => $actor->id, 'to_status' => 'draft']);
            Audit::record('quotation.created', $quote, $actor, $tenant->id);

            return $quote;
        });
    }

    public function updateDraft(Quotation $quote, User $actor, array $data): Quotation
    {
        return DB::transaction(function () use ($quote, $actor, $data): Quotation {
            $quote = Quotation::forTenant($quote->tenant_id)->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->ensureActor($quote->tenant, $actor);
            if ($quote->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Solo se puede editar un borrador. Duplicá el presupuesto para cambiar uno emitido.']);
            }
            if (isset($data['price_list_id']) && $data['price_list_id'] !== $quote->price_list_id) {
                $list = PriceList::forTenant($quote->tenant_id)->whereKey($data['price_list_id'])->where('is_active', true)->firstOrFail();
                abort_if($list->type === 'internal' || ($list->type !== 'public' && ! in_array(request()->attributes->get('activeMembership')?->role, ['owner', 'admin'], true)), 403);
                $quote->price_list_id = $list->id;
                $quote->items()->delete();
            }
            $quote->fill(collect($data)->only(['notes', 'internal_notes', 'valid_until', 'sale_modality', 'installment_count', 'installment_frequency'])->all());
            if ($quote->sale_modality === 'financed' && (! $quote->installment_count || ! $quote->installment_frequency)) {
                throw ValidationException::withMessages(['installment_count' => 'Definí cantidad y frecuencia de cuotas propuestas.']);
            }
            if ($quote->sale_modality === 'cash') {
                $quote->installment_count = null;
                $quote->installment_frequency = null;
            }
            $quote->save();
            if (array_key_exists('items', $data)) {
                $this->replaceItems($quote, $data['items']);
            }
            $this->recalculate($quote, $data['down_payment_amount'] ?? null);
            Audit::record('quotation.updated', $quote, $actor, $quote->tenant_id);

            return $quote->fresh();
        });
    }

    public function transition(Quotation $quote, User $actor, string $to, ?string $reason = null): Quotation
    {
        return DB::transaction(function () use ($quote, $actor, $to, $reason): Quotation {
            $quote = Quotation::forTenant($quote->tenant_id)->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->ensureActor($quote->tenant, $actor);
            if (! in_array($to, self::TRANSITIONS[$quote->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'La transición de estado no está permitida.']);
            }
            if ($to === 'pending_review' && ! $quote->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Agregá al menos un producto.']);
            }
            if ($to === 'expired' && (! $quote->valid_until || ! $quote->valid_until->isBefore(today()))) {
                throw ValidationException::withMessages(['valid_until' => 'El presupuesto todavía no está vencido.']);
            }
            if ($to === 'approved' && $quote->seller_id === $actor->id) {
                throw ValidationException::withMessages(['status' => 'No podés aprobar tu propio presupuesto.']);
            }
            if (in_array($to, ['pending_review', 'approved', 'sent'], true) && $quote->valid_until?->isBefore(today())) {
                throw ValidationException::withMessages(['valid_until' => 'El presupuesto ya está vencido.']);
            }
            $from = $quote->status;
            $quote->status = $to;
            if ($to === 'pending_review') {
                $quote->submitted_at = now();
            }
            if ($to === 'accepted') {
                $quote->accepted_at = now();
            }
            if ($to === 'rejected') {
                $quote->rejected_at = now();
            }
            $quote->save();
            QuotationStatusHistory::create(['tenant_id' => $quote->tenant_id, 'quotation_id' => $quote->id, 'actor_user_id' => $actor->id, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason]);
            Audit::record('quotation.'.$to, $quote, $actor, $quote->tenant_id, ['from' => $from]);

            return $quote;
        });
    }

    public function duplicate(Quotation $quote, User $actor): Quotation
    {
        return DB::transaction(function () use ($quote, $actor): Quotation {
            $copy = $this->create($quote->tenant, $actor, ['customer_id' => $quote->customer_id, 'price_list_id' => $quote->price_list_id]);
            $copy->update($quote->only(['notes', 'valid_until', 'sale_modality', 'installment_count', 'installment_frequency', 'subtotal', 'discount_amount', 'total_amount', 'down_payment_amount', 'financed_amount']));
            foreach ($quote->items as $item) {
                QuotationItem::create(array_merge($item->only(['product_id', 'description', 'sku_snapshot', 'quantity', 'unit_price', 'discount_amount', 'line_total', 'position']), ['tenant_id' => $quote->tenant_id, 'quotation_id' => $copy->id]));
            }
            Audit::record('quotation.duplicated', $copy, $actor, $quote->tenant_id, ['source_id' => $quote->id]);

            return $copy;
        });
    }

    public function activity(Quotation $quote, User $actor, array $data): QuotationActivity
    {
        return DB::transaction(function () use ($quote, $actor, $data): QuotationActivity {
            $this->ensureActor($quote->tenant, $actor);
            $activity = QuotationActivity::create(array_merge($data, ['tenant_id' => $quote->tenant_id, 'quotation_id' => $quote->id, 'user_id' => $actor->id, 'occurred_at' => now()]));
            Audit::record('quotation.activity_added', $activity, $actor, $quote->tenant_id);

            return $activity;
        });
    }

    private function ensureActor(Tenant $tenant, User $actor): void
    {
        abort_unless($tenant->status === 'active' && $actor->status === 'active'
            && TenantMembership::forTenant($tenant)->where('user_id', $actor->id)->where('status', 'active')->exists(), 403);
    }

    private function replaceItems(Quotation $quote, array $items): void
    {
        $quote->items()->delete();
        $list = PriceList::forTenant($quote->tenant_id)->findOrFail($quote->price_list_id);
        foreach ($items as $index => $item) {
            $product = Product::forTenant($quote->tenant_id)->findOrFail($item['product_id']);
            $price = app(PriceService::class)->applicable($product, $list, now());
            $gross = Decimal::line((string) $item['quantity'], $price->amount);
            QuotationItem::create([
                'tenant_id' => $quote->tenant_id, 'quotation_id' => $quote->id, 'product_id' => $product->id,
                'description' => $product->name, 'sku_snapshot' => $product->sku,
                'quantity' => $item['quantity'], 'unit_price' => $price->amount,
                'discount_amount' => '0.00', 'line_total' => Decimal::format($gross), 'position' => $index + 1,
            ]);
        }
    }

    private function recalculate(Quotation $quote, ?string $downPayment): void
    {
        $subtotal = 0;
        foreach ($quote->items()->get() as $item) {
            $subtotal += Decimal::toInteger($item->line_total);
        }
        $down = $quote->sale_modality === 'cash' ? $subtotal : Decimal::toInteger($downPayment ?? (string) $quote->down_payment_amount);
        if ($down > $subtotal) {
            throw ValidationException::withMessages(['down_payment_amount' => 'El anticipo no puede superar el total.']);
        }
        $quote->update([
            'subtotal' => Decimal::format($subtotal), 'discount_amount' => '0.00', 'total_amount' => Decimal::format($subtotal),
            'down_payment_amount' => Decimal::format($down), 'financed_amount' => Decimal::format($subtotal - $down),
            'estimated_installment_amount' => null,
        ]);
    }

    private function customerSnapshot(Customer $customer): array
    {
        return $customer->only(['customer_number', 'first_name', 'last_name', 'document_type', 'document_number', 'email', 'phone', 'whatsapp_phone']);
    }
}
