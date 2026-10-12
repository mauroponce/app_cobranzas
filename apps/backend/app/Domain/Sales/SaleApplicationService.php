<?php

namespace App\Domain\Sales;

use App\Domain\Commercial\CommercialNumber;
use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Models\CreditAssessment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SaleApplication;
use App\Models\SaleApplicationItem;
use App\Models\SaleApplicationStatusHistory;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaleApplicationService
{
    public function fromQuotation(Quotation $quotation, User $actor): SaleApplication
    {
        app(SalesAuthorization::class)->require($quotation->tenant_id, $actor, 'sales.create');

        return DB::transaction(function () use ($quotation, $actor): SaleApplication {
            $quotation = Quotation::forTenant($quotation->tenant_id)->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            if ($quotation->status !== 'accepted' || ! $quotation->items()->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Se requiere un presupuesto aceptado con ítems.']);
            }
            if (SaleApplication::forTenant($quotation->tenant_id)->where('quotation_id', $quotation->id)->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Este presupuesto ya tiene una solicitud.']);
            }
            $application = $this->create($quotation->tenant, $actor, [
                'quotation_id' => $quotation->id, 'customer_id' => $quotation->customer_id, 'seller_id' => $quotation->seller_id,
                'sale_type' => $quotation->sale_modality, 'requested_down_payment' => $quotation->down_payment_amount,
                'requested_installment_count' => $quotation->installment_count, 'requested_frequency' => $quotation->installment_frequency,
                'items' => $quotation->items->map(fn ($item) => $item->only(['product_id', 'description', 'sku_snapshot', 'quantity', 'unit_price', 'discount_amount', 'line_total']))->all(),
            ]);

            return $application;
        });
    }

    public function direct(Tenant $tenant, User $actor, array $data): SaleApplication
    {
        app(SalesAuthorization::class)->require($tenant->id, $actor, 'sales.create_direct');

        unset($data['quotation_id'], $data['seller_id']);

        return DB::transaction(fn (): SaleApplication => $this->create($tenant, $actor, $data));
    }

    public function replaceDirectItems(SaleApplication $application, User $actor, array $items): SaleApplication
    {
        app(SalesAuthorization::class)->require($application->tenant_id, $actor, 'sales.create_direct');

        return DB::transaction(function () use ($application, $actor, $items): SaleApplication {
            $application = $this->locked($application);
            if ($application->quotation_id || ! in_array($application->status, ['draft', 'changes_requested'], true)) {
                throw ValidationException::withMessages(['status' => 'Solo se pueden editar los ítems de solicitudes directas en borrador.']);
            }
            $application->items()->delete();
            $total = $this->storeItems($application, $items);
            $down = $application->sale_type === 'cash' ? $total : Decimal::toInteger($application->requested_down_payment);
            if ($down >= $total && $application->sale_type === 'financed') {
                throw ValidationException::withMessages(['requested_down_payment' => 'El anticipo debe ser menor que el total financiado.']);
            }
            $application->update(['requested_total' => Decimal::format($total), 'requested_down_payment' => Decimal::format($down)]);
            Audit::record('sale_application.items_updated', $application, $actor, $application->tenant_id);

            return $application;
        });
    }

    public function updateTerms(SaleApplication $application, User $actor, array $terms): SaleApplication
    {
        app(SalesAuthorization::class)->require($application->tenant_id, $actor, 'sales.create');

        return DB::transaction(function () use ($application, $actor, $terms): SaleApplication {
            $application = $this->locked($application);
            abort_unless($application->seller_id === $actor->id, 403);
            if (! in_array($application->status, ['draft', 'changes_requested'], true)) {
                throw ValidationException::withMessages(['status' => 'Las condiciones aprobadas no pueden editarse.']);
            }
            $total = Decimal::toInteger($application->requested_total);
            $down = $application->sale_type === 'cash' ? $total : Decimal::toInteger((string) ($terms['requested_down_payment'] ?? $application->requested_down_payment));
            if ($down > $total || ($application->sale_type === 'financed' && $down === $total)) {
                throw ValidationException::withMessages(['requested_down_payment' => 'El anticipo propuesto no es válido.']);
            }
            $count = $terms['requested_installment_count'] ?? $application->requested_installment_count;
            $frequency = $terms['requested_frequency'] ?? $application->requested_frequency;
            if ($application->sale_type === 'financed' && ((! is_int($count) && ! ctype_digit((string) $count)) || $count < 1 || $count > 120 || ! in_array($frequency, ['daily', 'weekly', 'biweekly', 'monthly'], true))) {
                throw ValidationException::withMessages(['requested_installment_count' => 'Definí cantidad y frecuencia válidas.']);
            }
            $application->update(['requested_down_payment' => Decimal::format($down),
                'requested_installment_count' => $application->sale_type === 'financed' ? $count : null,
                'requested_frequency' => $application->sale_type === 'financed' ? $frequency : null,
                'requested_first_due_date' => $application->sale_type === 'financed' ? ($terms['requested_first_due_date'] ?? $application->requested_first_due_date) : null]);
            Audit::record('sale_application.terms_updated', $application, $actor, $application->tenant_id);

            return $application;
        });
    }

    public function requestChanges(SaleApplication $application, User $actor, string $reason): SaleApplication
    {
        app(SalesAuthorization::class)->require($application->tenant_id, $actor, 'sales.approve');

        return DB::transaction(function () use ($application, $actor, $reason): SaleApplication {
            $application = $this->locked($application);
            if ($application->status !== 'pending_review') {
                throw ValidationException::withMessages(['status' => 'La solicitud no está en revisión.']);
            }
            $application->update(['status' => 'changes_requested']);
            $this->history($application, $actor, 'pending_review', 'changes_requested', $reason);
            Audit::record('sale_application.changes_requested', $application, $actor, $application->tenant_id);

            return $application;
        });
    }

    private function create(Tenant $tenant, User $actor, array $data): SaleApplication
    {
        Customer::forTenant($tenant)->whereKey($data['customer_id'])->where('status', 'active')->firstOrFail();
        if (! in_array($data['sale_type'], ['cash', 'financed'], true) || empty($data['items'])) {
            throw ValidationException::withMessages(['items' => 'La solicitud requiere modalidad e ítems válidos.']);
        }
        $total = 0;
        foreach ($data['items'] as $item) {
            $line = Decimal::line((string) $item['quantity'], (string) $item['unit_price']);
            if ($line <= 0 || $line !== Decimal::toInteger((string) $item['line_total']) || Decimal::toInteger((string) ($item['discount_amount'] ?? '0.00')) !== 0) {
                throw ValidationException::withMessages(['items' => 'Los importes de los ítems no coinciden.']);
            }
            $total += $line;
        }
        $down = $data['sale_type'] === 'cash' ? $total : Decimal::toInteger((string) ($data['requested_down_payment'] ?? '0.00'));
        if ($down > $total || ($data['sale_type'] === 'financed' && ($down === $total || empty($data['requested_installment_count']) || empty($data['requested_frequency'])))) {
            throw ValidationException::withMessages(['conditions' => 'Las condiciones financieras no son válidas.']);
        }
        $application = SaleApplication::create([
            'tenant_id' => $tenant->id, 'application_number' => CommercialNumber::next($tenant->id, 'sale_application', 'SOL-'),
            'quotation_id' => $data['quotation_id'] ?? null, 'customer_id' => $data['customer_id'], 'seller_id' => $data['seller_id'] ?? $actor->id,
            'sale_type' => $data['sale_type'], 'status' => 'draft', 'requested_total' => Decimal::format($total),
            'requested_down_payment' => Decimal::format($down), 'requested_financing_charge' => '0.00',
            'requested_installment_count' => $data['sale_type'] === 'financed' ? $data['requested_installment_count'] : null,
            'requested_frequency' => $data['sale_type'] === 'financed' ? $data['requested_frequency'] : null,
            'requested_first_due_date' => $data['requested_first_due_date'] ?? null,
        ]);
        $this->storeItems($application, $data['items']);
        $this->history($application, $actor, null, 'draft');
        Audit::record('sale_application.created', $application, $actor, $tenant->id);

        return $application;
    }

    public function submit(SaleApplication $application, User $actor): SaleApplication
    {
        app(SalesAuthorization::class)->require($application->tenant_id, $actor, 'sales.create');

        return DB::transaction(function () use ($application, $actor): SaleApplication {
            $application = $this->locked($application);
            if (! in_array($application->status, ['draft', 'changes_requested', 'pending_documents'], true)) {
                throw ValidationException::withMessages(['status' => 'La solicitud no admite revisión.']);
            }
            $from = $application->status;
            $application->update(['status' => 'pending_review', 'submitted_at' => now()]);
            $this->history($application, $actor, $from, 'pending_review');
            Audit::record('sale_application.submitted', $application, $actor, $application->tenant_id);

            return $application;
        });
    }

    public function requestDocuments(SaleApplication $application, User $actor, string $reason): SaleApplication
    {
        app(SalesAuthorization::class)->require($application->tenant_id, $actor, 'sales.approve');

        return DB::transaction(function () use ($application, $actor, $reason): SaleApplication {
            $application = $this->locked($application);
            if ($application->status !== 'pending_review') {
                throw ValidationException::withMessages(['status' => 'La solicitud no está en revisión.']);
            }
            $application->update(['status' => 'pending_documents']);
            $this->history($application, $actor, 'pending_review', 'pending_documents', $reason);
            Audit::record('sale_application.documents_requested', $application, $actor, $application->tenant_id);

            return $application;
        });
    }

    public function assess(SaleApplication $application, User $actor, bool $approved, array $terms = [], ?string $notes = null): CreditAssessment
    {
        app(SalesAuthorization::class)->require($application->tenant_id, $actor, 'sales.approve');

        return DB::transaction(function () use ($application, $actor, $approved, $terms, $notes): CreditAssessment {
            $application = $this->locked($application);
            if ($application->status !== 'pending_review' || $application->seller_id === $actor->id) {
                throw ValidationException::withMessages(['status' => 'La evaluación no puede ser resuelta por este usuario o en este estado.']);
            }
            $total = Decimal::toInteger($application->requested_total);
            $down = Decimal::toInteger((string) ($terms['down_payment'] ?? $application->requested_down_payment));
            $charge = Decimal::toInteger((string) ($terms['financing_charge'] ?? '0.00'));
            if ($down > $total || ($application->sale_type === 'financed' && $down === $total) || ($application->sale_type === 'cash' && ($charge !== 0 || $down !== $total))) {
                throw ValidationException::withMessages(['terms' => 'Las condiciones aprobadas no son válidas.']);
            }
            $count = $application->sale_type === 'financed' ? (int) ($terms['installment_count'] ?? $application->requested_installment_count) : null;
            $frequency = $application->sale_type === 'financed' ? ($terms['frequency'] ?? $application->requested_frequency) : null;
            if ($approved && $application->sale_type === 'financed' && (! $count || ! in_array($frequency, ['daily', 'weekly', 'biweekly', 'monthly'], true))) {
                throw ValidationException::withMessages(['terms' => 'Definí un cronograma válido.']);
            }
            if ($approved && $application->sale_type === 'financed') {
                $firstDue = $application->requested_first_due_date ? CarbonImmutable::parse($application->requested_first_due_date) : null;
                if (! $firstDue || $firstDue->toDateString() < CarbonImmutable::today($application->tenant->timezone)->toDateString()) {
                    throw ValidationException::withMessages(['requested_first_due_date' => 'Definí una primera fecha de vencimiento vigente.']);
                }
                app(InstallmentSchedule::class)->generate($total - $down + $charge, $count, $frequency, $firstDue);
            }
            $assessment = CreditAssessment::create([
                'tenant_id' => $application->tenant_id, 'sale_application_id' => $application->id, 'status' => $approved ? 'approved' : 'rejected',
                'requested_amount' => $application->requested_total, 'approved_amount' => $approved ? $application->requested_total : null,
                'approved_down_payment' => $approved ? Decimal::format($down) : null,
                'approved_financing_charge' => $approved ? Decimal::format($charge) : null,
                'approved_installment_count' => $approved ? $count : null, 'approved_frequency' => $approved ? $frequency : null,
                'reviewer_id' => $actor->id, 'review_notes' => $notes, 'reviewed_at' => now(),
            ]);
            $to = $approved ? 'approved' : 'rejected';
            $application->update(['status' => $to, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->history($application, $actor, 'pending_review', $to, $notes);
            Audit::record('sale_application.'.$to, $application, $actor, $application->tenant_id);

            return $assessment;
        });
    }

    private function locked(SaleApplication $application): SaleApplication
    {
        return SaleApplication::forTenant($application->tenant_id)->whereKey($application->id)->lockForUpdate()->firstOrFail();
    }

    private function storeItems(SaleApplication $application, array $items): int
    {
        if ($items === [] || count($items) > 50) {
            throw ValidationException::withMessages(['items' => 'La solicitud requiere entre uno y cincuenta ítems.']);
        }
        $total = 0;
        foreach ($items as $index => $item) {
            if (! empty($item['product_id'])) {
                Product::forTenant($application->tenant_id)->whereKey($item['product_id'])->firstOrFail();
            }
            $line = Decimal::line((string) $item['quantity'], (string) $item['unit_price']);
            if ($line <= 0 || $line !== Decimal::toInteger((string) $item['line_total']) || Decimal::toInteger((string) ($item['discount_amount'] ?? '0.00')) !== 0) {
                throw ValidationException::withMessages(['items' => 'Los importes de los ítems no coinciden.']);
            }
            SaleApplicationItem::create(['tenant_id' => $application->tenant_id, 'sale_application_id' => $application->id,
                'product_id' => $item['product_id'] ?? null, 'description' => $item['description'], 'sku_snapshot' => $item['sku_snapshot'] ?? null,
                'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'discount_amount' => '0.00',
                'line_total' => $item['line_total'], 'position' => $index + 1]);
            $total += $line;
        }

        return $total;
    }

    private function history(SaleApplication $application, User $actor, ?string $from, string $to, ?string $reason = null): void
    {
        SaleApplicationStatusHistory::create(['tenant_id' => $application->tenant_id, 'sale_application_id' => $application->id, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason]);
    }
}
