<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomerCredit extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'customer_id', 'payment_id', 'amount', 'remaining_amount', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'remaining_amount' => 'decimal:2'];
    }
}
