<?php

namespace App\Domain\Sales;

use App\Domain\Commercial\CommercialNumber;
use App\Domain\Commercial\Decimal;
use App\Domain\Tenancy\Audit;
use App\Models\CreditAssessment;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerDocument;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Models\SaleApplication;
use App\Models\SaleApplicationStatusHistory;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmSaleAction
{
    public function execute(SaleApplication $application, User $actor): Sale
    {
        app(SalesAuthorization::class)->require($application->tenant_id, $actor, 'sales.confirm');

        return DB::transaction(function () use ($application, $actor): Sale {
            $application = SaleApplication::forTenant($application->tenant_id)->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== 'approved' || Sale::forTenant($application->tenant_id)->where('sale_application_id', $application->id)->exists()) {
                throw ValidationException::withMessages(['status' => 'La solicitud no está disponible para confirmar.']);
            }
            $assessment = CreditAssessment::forTenant($application->tenant_id)->where('sale_application_id', $application->id)->where('status', 'approved')->latest()->first();
            if (! $assessment || $assessment->approved_amount !== $application->requested_total) {
                throw ValidationException::withMessages(['assessment' => 'Falta una evaluación aprobada para el importe actual.']);
            }
            if ($application->sale_type === 'financed' && ! CustomerDocument::forTenant($application->tenant_id)->where('customer_id', $application->customer_id)
                ->where(fn ($q) => $q->where('sale_application_id', $application->id)->orWhereNull('sale_application_id'))
                ->where('document_type', 'identity')->where('verification_status', 'verified')->whereHas('storedDocument', fn ($q) => $q->where('status', 'ready'))->exists()) {
                throw ValidationException::withMessages(['documents' => 'Se requiere un documento de identidad verificado.']);
            }
            $total = Decimal::toInteger($assessment->approved_amount);
            $down = Decimal::toInteger($assessment->approved_down_payment);
            $charge = Decimal::toInteger($assessment->approved_financing_charge);
            $principal = $total - $down;
            if ($total <= 0 || $down > $total || ($application->sale_type === 'cash' && ($principal !== 0 || $charge !== 0))) {
                throw ValidationException::withMessages(['terms' => 'Las condiciones aprobadas no son válidas.']);
            }
            $items = $application->items()->orderBy('position')->get();
            if ($items->isEmpty() || $items->sum(fn ($item) => Decimal::toInteger($item->line_total)) !== $total) {
                throw ValidationException::withMessages(['items' => 'El total de ítems no coincide con la evaluación.']);
            }
            $firstDueDate = $application->requested_first_due_date ? CarbonImmutable::parse($application->requested_first_due_date)->startOfDay() : null;
            $businessToday = CarbonImmutable::today($application->tenant->timezone);
            if ($application->sale_type === 'financed' && (! $firstDueDate || $firstDueDate->toDateString() < $businessToday->toDateString())) {
                throw ValidationException::withMessages(['requested_first_due_date' => 'Definí una primera fecha de vencimiento vigente.']);
            }
            $sale = Sale::create([
                'tenant_id' => $application->tenant_id, 'sale_number' => CommercialNumber::next($application->tenant_id, 'sale', 'VEN-'),
                'sale_application_id' => $application->id, 'quotation_id' => $application->quotation_id, 'customer_id' => $application->customer_id,
                'seller_id' => $application->seller_id, 'sale_type' => $application->sale_type, 'status' => 'confirmed', 'currency' => 'ARS',
                'total_amount' => Decimal::format($total), 'subtotal' => Decimal::format($total), 'discount_amount' => '0.00',
                'down_payment_amount' => Decimal::format($down), 'financed_principal' => Decimal::format($principal),
                'financing_charge_amount' => Decimal::format($charge), 'financed_total' => Decimal::format($principal + $charge),
                'confirmed_at' => now(), 'confirmed_by' => $actor->id,
            ]);
            foreach ($items as $item) {
                SaleItem::create(['tenant_id' => $sale->tenant_id, 'sale_id' => $sale->id, 'product_id' => $item->product_id,
                    'description' => $item->description, 'sku_snapshot' => $item->sku_snapshot, 'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price, 'line_total' => $item->line_total, 'position' => $item->position]);
            }
            if ($application->sale_type === 'cash') {
                $this->obligation($sale, null, 'cash', $total, $businessToday);
            } else {
                if ($down > 0) {
                    $this->obligation($sale, null, 'down_payment', $down, $businessToday);
                }
                $schedule = app(InstallmentSchedule::class)->generate($principal + $charge, (int) $assessment->approved_installment_count, $assessment->approved_frequency, $firstDueDate);
                $plan = InstallmentPlan::create([
                    'tenant_id' => $sale->tenant_id, 'sale_id' => $sale->id, 'customer_id' => $sale->customer_id, 'installment_count' => count($schedule),
                    'frequency' => $assessment->approved_frequency, 'principal_amount' => Decimal::format($principal),
                    'financial_charge_amount' => Decimal::format($charge), 'total_amount' => Decimal::format($principal + $charge),
                    'first_due_date' => $firstDueDate->toDateString(), 'rounding_policy' => 'last_installment', 'schedule_snapshot' => $schedule,
                ]);
                foreach ($schedule as $row) {
                    $installment = Installment::create(['tenant_id' => $sale->tenant_id, 'plan_id' => $plan->id,
                        'sale_id' => $sale->id, 'customer_id' => $sale->customer_id, 'sequence' => $row['sequence'], 'due_date' => $row['due_date'],
                        'amount' => Decimal::format($row['amount_cents']), 'outstanding_amount' => Decimal::format($row['amount_cents']), 'status' => 'pending']);
                    $this->obligation($sale, $installment, 'installment', $row['amount_cents'], CarbonImmutable::parse($row['due_date']));
                }
            }
            $application->update(['status' => 'converted']);
            SaleApplicationStatusHistory::create(['tenant_id' => $sale->tenant_id, 'sale_application_id' => $application->id, 'actor_id' => $actor->id, 'from_status' => 'approved', 'to_status' => 'converted']);
            Audit::record('sale.confirmed', $sale, $actor, $sale->tenant_id);
            GenerateSaleDocuments::dispatch($sale->tenant_id, $sale->id)->afterCommit();

            return $sale;
        });
    }

    private function obligation(Sale $sale, ?Installment $installment, string $type, int $cents, CarbonImmutable $dueDate): void
    {
        CustomerAccountEntry::create(['tenant_id' => $sale->tenant_id, 'customer_id' => $sale->customer_id, 'sale_id' => $sale->id,
            'installment_id' => $installment?->id, 'entry_type' => $type, 'amount' => Decimal::format($cents),
            'outstanding_amount' => Decimal::format($cents), 'due_date' => $dueDate->toDateString(), 'status' => 'open']);
    }
}
