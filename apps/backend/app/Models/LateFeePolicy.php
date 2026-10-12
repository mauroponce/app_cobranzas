<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateFeePolicy extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'late_fee_policies';

    protected $fillable = ['tenant_id', 'version', 'effective_from', 'effective_until', 'grace_days', 'daily_basis_points', 'cap_basis_points', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_until' => 'date'];
    }
}
