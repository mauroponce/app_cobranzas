<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionZone extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'code', 'name', 'description', 'status'];

    public function assignments(): HasMany
    {
        return $this->hasMany(CustomerCollectionZoneAssignment::class, 'zone_id');
    }
}
