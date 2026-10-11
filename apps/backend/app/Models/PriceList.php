<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    use BelongsToTenant, HasUuids;

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    protected $table = 'price_lists';

    protected $fillable = ['tenant_id', 'name', 'code', 'type', 'currency', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
