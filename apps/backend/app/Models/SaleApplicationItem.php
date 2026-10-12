<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleApplicationItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'sale_application_items';

    protected $fillable = ['tenant_id', 'sale_application_id', 'product_id', 'description', 'sku_snapshot', 'quantity', 'unit_price', 'discount_amount', 'line_total', 'position'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(SaleApplication::class, 'sale_application_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'line_total' => 'decimal:2'];
    }
}
