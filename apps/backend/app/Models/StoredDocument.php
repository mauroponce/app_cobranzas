<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoredDocument extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $table = 'stored_documents';

    protected $fillable = ['tenant_id', 'disk', 'bucket', 'object_key', 'original_name', 'mime_type', 'size_bytes', 'checksum', 'status', 'uploaded_by'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
