<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantFeature extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'feature_id', 'is_enabled', 'enabled_at', 'disabled_at', 'updated_by'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'enabled_at' => 'datetime', 'disabled_at' => 'datetime'];
    }
}
