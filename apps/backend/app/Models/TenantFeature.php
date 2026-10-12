<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeature extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'feature_id', 'is_available', 'available_at', 'is_enabled', 'enabled_at', 'disabled_at', 'updated_by'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean', 'available_at' => 'datetime', 'is_enabled' => 'boolean', 'enabled_at' => 'datetime', 'disabled_at' => 'datetime'];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
