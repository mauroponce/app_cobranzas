<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    protected $table = 'products';

    protected $fillable = ['tenant_id', 'sku', 'name', 'description', 'category_id', 'brand', 'unit', 'status'];
}
