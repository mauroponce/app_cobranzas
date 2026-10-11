<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAdministrator extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'granted_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
