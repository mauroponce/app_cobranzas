<?php

namespace App\Domain\Sales;

use App\Models\CustomerDocument;
use App\Models\SaleDocument;
use App\Models\StoredDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentStorageService
{
    private const MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function upload(Tenant $tenant, User $actor, UploadedFile $file): StoredDocument
    {
        app(SalesAuthorization::class)->require($tenant->id, $actor, 'sales.documents.manage');
        $mime = $file->getMimeType();
        if (! in_array($mime, self::MIMES, true) || $file->getSize() > 10 * 1024 * 1024 || $file->getSize() <= 0) {
            throw ValidationException::withMessages(['document' => 'El archivo debe ser PDF, JPG o PNG y medir hasta 10 MB.']);
        }

        return $this->store($tenant->id, $actor->id, $file->get(), $mime, $file->getClientOriginalName());
    }

    public function store(string $tenantId, string $actorId, string $content, string $mime, string $name): StoredDocument
    {
        if (! in_array($mime, self::MIMES, true) || strlen($content) > 10 * 1024 * 1024 || strlen($content) === 0) {
            throw ValidationException::withMessages(['document' => 'El archivo no es válido.']);
        }
        $key = 'tenants/'.$tenantId.'/documents/'.Str::uuid().'/'.Str::uuid();
        $disk = Storage::disk('r2');
        $document = StoredDocument::create(['tenant_id' => $tenantId, 'disk' => 'r2', 'bucket' => config('filesystems.disks.r2.bucket') ?: 'fake',
            'object_key' => $key, 'original_name' => basename($name), 'mime_type' => $mime, 'size_bytes' => strlen($content),
            'checksum' => hash('sha256', $content), 'status' => 'pending_upload', 'uploaded_by' => $actorId]);
        try {
            if (! $disk->put($key, $content, ['visibility' => 'private', 'ContentType' => $mime])) {
                throw new \RuntimeException('Document upload failed.');
            }
            $document->update(['status' => 'ready']);
        } catch (\Throwable $error) {
            $disk->delete($key);
            $document->update(['status' => 'failed']);
            throw $error;
        }

        return $document;
    }

    public function download(StoredDocument $document): string
    {
        if ($document->status !== 'ready') {
            abort(404);
        }

        return Storage::disk($document->disk)->get($document->object_key);
    }

    public function temporaryUrl(StoredDocument $document): string
    {
        abort_unless($document->status === 'ready', 404);

        return Storage::disk($document->disk)->temporaryUrl($document->object_key, now()->addMinutes(5));
    }

    public function delete(StoredDocument $document, User $actor): void
    {
        app(SalesAuthorization::class)->require($document->tenant_id, $actor, 'sales.documents.manage');

        $this->discardUnlinked($document);
    }

    public function discardUnlinked(StoredDocument $document): void
    {
        if (CustomerDocument::forTenant($document->tenant_id)->where('stored_document_id', $document->id)->exists()
            || SaleDocument::forTenant($document->tenant_id)->where('stored_document_id', $document->id)->exists()) {
            throw ValidationException::withMessages(['document' => 'No se puede eliminar un documento asociado.']);
        }
        Storage::disk($document->disk)->delete($document->object_key);
        $document->update(['status' => 'deleted']);
        $document->delete();
    }
}
