<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CollectionIncident extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'route_id', 'visit_id', 'declaration_id', 'type', 'status', 'details', 'reported_by', 'resolved_by', 'resolved_at'];

    protected function casts(): array { return ['resolved_at' => 'datetime']; }
}
