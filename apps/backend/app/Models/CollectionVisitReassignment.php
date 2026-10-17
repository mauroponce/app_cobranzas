<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionVisitReassignment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'source_visit_id', 'target_visit_id', 'from_route_id', 'to_route_id', 'from_collector_membership_id', 'to_collector_membership_id', 'reason', 'reassigned_by_user_id'];

    public function sourceVisit(): BelongsTo
    {
        return $this->belongsTo(CollectionVisit::class, 'source_visit_id');
    }

    public function targetVisit(): BelongsTo
    {
        return $this->belongsTo(CollectionVisit::class, 'target_visit_id');
    }

    public function fromCollector(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'from_collector_membership_id');
    }

    public function toCollector(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'to_collector_membership_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_by_user_id');
    }
}
