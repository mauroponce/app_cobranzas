<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionVisit extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'route_id', 'sale_id', 'customer_id', 'sequence', 'status', 'version', 'scheduled_amount', 'obligation_snapshot', 'outcome', 'no_payment_reason', 'no_payment_notes', 'recorded_at', 'received_at'];

    protected function casts(): array
    {
        return ['scheduled_amount' => 'decimal:2', 'obligation_snapshot' => 'array', 'recorded_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function route(): BelongsTo { return $this->belongsTo(CollectionRoute::class, 'route_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
}
