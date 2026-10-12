<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    use HasUuids;

    protected $fillable = ['key', 'feature_id', 'description', 'allowed_scopes'];

    protected function casts(): array
    {
        return ['allowed_scopes' => 'array'];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
