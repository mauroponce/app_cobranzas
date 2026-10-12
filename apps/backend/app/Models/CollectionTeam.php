<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionTeam extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'name', 'description', 'status'];

    public function members(): HasMany
    {
        return $this->hasMany(CollectionTeamMember::class, 'team_id');
    }
}
