<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionDeclaration extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'visit_id', 'customer_id', 'declared_by_user_id', 'operation_id', 'payload_hash', 'amount', 'status', 'declared_at', 'received_at', 'approved_by_user_id', 'approved_at', 'rejection_reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'declared_at' => 'datetime', 'received_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CollectionVisit::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(CollectionDeclarationComponent::class, 'declaration_id');
    }
}
