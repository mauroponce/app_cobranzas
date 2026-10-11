<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InstallmentPlan extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'sale_id', 'installment_count', 'frequency', 'principal_amount', 'financial_charge_amount', 'total_amount'];

    protected function casts(): array
    {
        return ['principal_amount' => 'decimal:2', 'financial_charge_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }
}
