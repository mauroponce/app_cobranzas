<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'sale_number', 'customer_id', 'seller_id', 'sale_type', 'status', 'total_amount', 'currency'];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2'];
    }
}
