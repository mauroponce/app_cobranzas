<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionIncident extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'route_id', 'visit_id', 'declaration_id', 'type', 'status', 'details', 'reported_by', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(CollectionRoute::class, 'route_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CollectionVisit::class, 'visit_id');
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(CollectionDeclaration::class, 'declaration_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
