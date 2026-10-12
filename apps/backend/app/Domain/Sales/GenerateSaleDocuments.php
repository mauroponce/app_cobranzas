<?php

namespace App\Domain\Sales;

use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class GenerateSaleDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $tenantId, public string $saleId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->tenantId.':'.$this->saleId))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(DocumentStorageService $storage): void
    {
        if (! Tenant::query()->whereKey($this->tenantId)->where('status', 'active')->exists()) {
            return;
        }
        $sale = Sale::forTenant($this->tenantId)->with(['items', 'customer', 'plan.installments', 'confirmedBy'])->find($this->saleId);
        if (! $sale || $sale->status !== 'confirmed') {
            return;
        }
        if (! TenantMembership::forTenant($this->tenantId)->where('user_id', $sale->confirmed_by)->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))->exists()) {
            return;
        }
        foreach (['summary', 'schedule', 'contract'] as $type) {
            if (SaleDocument::forTenant($this->tenantId)->where('sale_id', $sale->id)->where('document_type', $type)->exists()) {
                continue;
            }
            $pdf = Pdf::loadView('commercial.sales.documents.'.$type, compact('sale'))->output();
            $document = $storage->store($this->tenantId, $sale->confirmed_by, $pdf, 'application/pdf', $sale->sale_number.'-'.$type.'.pdf');
            try {
                SaleDocument::create(['tenant_id' => $this->tenantId, 'sale_id' => $sale->id, 'stored_document_id' => $document->id, 'document_type' => $type]);
            } catch (\Throwable $error) {
                $storage->discardUnlinked($document);
                throw $error;
            }
        }
    }
}
