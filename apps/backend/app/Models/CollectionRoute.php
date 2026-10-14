<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionRoute extends Model
{
    use BelongsToTenant, HasUuids;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['tenant_id', 'route_date', 'collector_membership_id', 'zone_id', 'name', 'description', 'status', 'created_by_membership_id', 'published_by_membership_id', 'published_at', 'cancelled_at', 'cancellation_reason', 'version'];

    protected function casts(): array
    {
        return ['route_date' => 'date', 'published_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function collectorMembership(): BelongsTo { return $this->belongsTo(TenantMembership::class, 'collector_membership_id'); }
    public function zone(): BelongsTo { return $this->belongsTo(CollectionZone::class); }
    public function stops(): HasMany { return $this->hasMany(CollectionRouteStop::class, 'route_id'); }
    public function visits(): HasMany { return $this->hasMany(CollectionVisit::class, 'route_id'); }
}
