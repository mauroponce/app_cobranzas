<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleApplication extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'sale_applications';

    protected $fillable = ['tenant_id', 'application_number', 'quotation_id', 'customer_id', 'seller_id', 'sale_type', 'status', 'requested_total', 'requested_down_payment', 'requested_financing_charge', 'requested_installment_count', 'requested_frequency', 'requested_first_due_date', 'notes', 'submitted_at', 'reviewed_by', 'reviewed_at'];

    public function items(): HasMany
    {
        return $this->hasMany(SaleApplicationItem::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(CreditAssessment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(SaleApplicationStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['requested_total' => 'decimal:2', 'requested_down_payment' => 'decimal:2', 'requested_financing_charge' => 'decimal:2', 'requested_first_due_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
