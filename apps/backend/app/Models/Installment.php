<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'plan_id', 'customer_id', 'sequence', 'due_date', 'amount', 'outstanding_amount', 'status'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount' => 'decimal:2', 'outstanding_amount' => 'decimal:2'];
    }
}
