<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'quotations';

    protected $fillable = ['tenant_id', 'quotation_number', 'customer_id', 'seller_id', 'status', 'price_list_id', 'currency', 'sale_modality', 'subtotal', 'discount_amount', 'total_amount', 'down_payment_amount', 'financed_amount', 'installment_count', 'installment_frequency', 'estimated_installment_amount', 'valid_until', 'notes', 'internal_notes', 'customer_snapshot', 'address_snapshot', 'submitted_at', 'accepted_at', 'rejected_at'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('position');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(QuotationStatusHistory::class)->orderBy('created_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(QuotationActivity::class)->orderByDesc('occurred_at');
    }

    public function saleApplication(): HasOne
    {
        return $this->hasOne(SaleApplication::class);
    }

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'down_payment_amount' => 'decimal:2', 'financed_amount' => 'decimal:2', 'estimated_installment_amount' => 'decimal:2', 'customer_snapshot' => 'array', 'address_snapshot' => 'array', 'valid_until' => 'date', 'submitted_at' => 'datetime', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime'];
    }
}
