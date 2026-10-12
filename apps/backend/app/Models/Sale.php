<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'sale_number', 'sale_application_id', 'quotation_id', 'customer_id', 'seller_id', 'sale_type', 'status', 'total_amount', 'currency', 'subtotal', 'discount_amount', 'down_payment_amount', 'financed_principal', 'financing_charge_amount', 'financed_total', 'confirmed_at', 'confirmed_by', 'cancelled_at', 'cancellation_reason'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(SaleApplication::class, 'sale_application_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function plan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class);
    }

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'down_payment_amount' => 'decimal:2', 'financed_principal' => 'decimal:2', 'financing_charge_amount' => 'decimal:2', 'financed_total' => 'decimal:2', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
