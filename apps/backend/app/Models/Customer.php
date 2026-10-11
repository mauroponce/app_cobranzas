<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    protected $table = 'customers';

    protected $fillable = ['tenant_id', 'customer_number', 'first_name', 'last_name', 'document_type', 'document_number', 'document_normalized', 'email', 'phone', 'whatsapp_phone', 'birth_date', 'notes', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }
}
