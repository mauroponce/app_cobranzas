<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSettlement extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'route_id', 'collector_id', 'presented_by_user_id', 'declared_amount', 'approved_amount', 'received_amount', 'status', 'submitted_at', 'presented_at', 'reviewed_by', 'reviewed_at', 'notes'];

    protected function casts(): array
    {
        return ['declared_amount' => 'decimal:2', 'approved_amount' => 'decimal:2', 'received_amount' => 'decimal:2', 'submitted_at' => 'datetime', 'presented_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(CollectionRoute::class, 'route_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CashSettlementItem::class, 'settlement_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presented_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
