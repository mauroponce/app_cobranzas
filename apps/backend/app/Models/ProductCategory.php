<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'product_categories';

    protected $fillable = ['tenant_id', 'name'];
}
