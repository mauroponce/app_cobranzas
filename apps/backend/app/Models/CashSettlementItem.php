<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSettlementItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'settlement_id', 'declaration_component_id', 'payment_id', 'amount', 'received_amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_amount' => 'decimal:2'];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CashSettlement::class, 'settlement_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CollectionDeclarationComponent::class, 'declaration_component_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
