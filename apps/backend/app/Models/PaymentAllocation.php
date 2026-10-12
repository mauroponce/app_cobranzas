<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'payment_id', 'customer_id', 'account_entry_id', 'installment_id', 'reversed_by_allocation_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
