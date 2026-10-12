<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashSettlementItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'settlement_id', 'payment_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
