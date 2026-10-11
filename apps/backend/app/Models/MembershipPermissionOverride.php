<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MembershipPermissionOverride extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'membership_id', 'permission_id', 'effect', 'scope', 'created_by'];
}
