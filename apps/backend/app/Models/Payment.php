<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'customer_id', 'received_by', 'method', 'origin', 'amount', 'unallocated_amount', 'currency', 'status', 'received_at', 'confirmed_at', 'external_reference', 'mobile_operation_id', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'unallocated_amount' => 'decimal:2', 'received_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
