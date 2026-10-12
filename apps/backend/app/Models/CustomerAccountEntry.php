<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccountEntry extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'customer_account_entries';

    protected $fillable = ['tenant_id', 'customer_id', 'sale_id', 'installment_id', 'entry_type', 'amount', 'outstanding_amount', 'due_date', 'status'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'outstanding_amount' => 'decimal:2', 'due_date' => 'date'];
    }
}
