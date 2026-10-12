<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCollectorAssignment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'customer_id', 'collector_membership_id', 'assigned_by_membership_id', 'assigned_at', 'unassigned_at', 'reason'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'unassigned_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function collectorMembership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'collector_membership_id');
    }

    public function assignedByMembership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'assigned_by_membership_id');
    }
}
