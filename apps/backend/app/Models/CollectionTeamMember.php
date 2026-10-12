<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionTeamMember extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'team_id', 'membership_id', 'team_role', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(CollectionTeam::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class);
    }
}
