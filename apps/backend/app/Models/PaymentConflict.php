<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentConflict extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'payment_id', 'type', 'status', 'details', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'resolved_at' => 'datetime'];
    }
}
