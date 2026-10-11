<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'product_prices';

    protected $fillable = ['tenant_id', 'price_list_id', 'product_id', 'amount', 'valid_from', 'valid_until'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'valid_from' => 'date', 'valid_until' => 'date'];
    }
}
