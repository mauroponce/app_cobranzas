<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'customer_id', 'received_by', 'received_by_user_id', 'registered_by_user_id', 'method', 'origin', 'source', 'amount', 'unallocated_amount', 'currency', 'status', 'received_at', 'confirmed_at', 'reversed_at', 'external_reference', 'reference', 'mobile_operation_id', 'client_operation_id', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'unallocated_amount' => 'decimal:2', 'received_at' => 'datetime', 'confirmed_at' => 'datetime', 'reversed_at' => 'datetime'];
    }
}
