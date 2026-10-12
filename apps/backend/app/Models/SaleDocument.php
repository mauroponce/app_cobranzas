<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDocument extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'sale_id', 'stored_document_id', 'document_type'];

    public function storedDocument(): BelongsTo
    {
        return $this->belongsTo(StoredDocument::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
