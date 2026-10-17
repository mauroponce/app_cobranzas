<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionDeclarationComponent extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'declaration_id', 'method', 'amount', 'status', 'confirmed_amount', 'payment_id', 'reconciled_by_user_id', 'reconciled_at', 'reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'confirmed_amount' => 'decimal:2', 'reconciled_at' => 'datetime'];
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(CollectionDeclaration::class, 'declaration_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
