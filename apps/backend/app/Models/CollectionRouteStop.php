<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionRouteStop extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'route_id', 'customer_id', 'customer_address_id', 'sequence', 'assigned_collector_membership_id', 'status', 'planned_notes', 'address_snapshot'];

    protected function casts(): array { return ['address_snapshot' => 'array']; }

    public function route(): BelongsTo { return $this->belongsTo(CollectionRoute::class, 'route_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function address(): BelongsTo { return $this->belongsTo(CustomerAddress::class, 'customer_address_id'); }
    public function assignedCollectorMembership(): BelongsTo { return $this->belongsTo(TenantMembership::class, 'assigned_collector_membership_id'); }
}
