<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDocument extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'customer_documents';

    protected $fillable = ['tenant_id', 'customer_id', 'stored_document_id', 'sale_application_id', 'document_type', 'verification_status', 'verified_by', 'verified_at', 'rejection_reason', 'uploaded_by'];

    public function storedDocument(): BelongsTo
    {
        return $this->belongsTo(StoredDocument::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SaleApplication::class, 'sale_application_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
