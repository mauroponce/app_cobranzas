<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'quotation_items';

    protected $fillable = ['tenant_id', 'quotation_id', 'product_id', 'description', 'sku_snapshot', 'quantity', 'unit_price', 'discount_amount', 'line_total', 'position'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'line_total' => 'decimal:2'];
    }
}
