<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $table = 'customer_addresses';

    protected $fillable = ['tenant_id', 'customer_id', 'label', 'street', 'street_number', 'floor', 'unit', 'neighborhood', 'city', 'province', 'postal_code', 'country', 'reference', 'latitude', 'longitude', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }
}
