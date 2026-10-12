<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPlan extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'sale_id', 'customer_id', 'installment_count', 'frequency', 'principal_amount', 'financial_charge_amount', 'total_amount', 'first_due_date', 'rounding_policy', 'schedule_snapshot'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'plan_id')->orderBy('sequence');
    }

    protected function casts(): array
    {
        return ['principal_amount' => 'decimal:2', 'financial_charge_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'first_due_date' => 'date', 'schedule_snapshot' => 'array'];
    }
}
