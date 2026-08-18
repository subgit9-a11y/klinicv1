<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\StorageProviderInterface;
use App\Models\Consultation;
use App\Models\Document;
use App\Models\IpdAdmission;
use App\Models\Patient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Document management service.
 *
 * Handles upload, retrieval, signed URL generation, and audit trail
 * for clinical documents. All files are stored privately via the
 * StorageProviderInterface; access is controlled via signed URLs.
 */
class DocumentService
{
    public function __construct(
        private readonly StorageProviderInterface $storage,
    ) {}

    /**
     * Upload a file and create a Document record.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function upload(
        UploadedFile $file,
        string $type,
        ?Model $attachable = null,
        ?int $uploadedBy = null,
        array $metadata = []
    ): Document {
        $tenantId = app(TenantContext::class)->id();

        $path = $this->buildPath($file, $tenantId, $attachable);
        $content = file_get_contents($file->getRealPath());

        $storedPath = $this->storage->store(
            $content,
            $path,
            $file->getMimeType()
        );

        return DB::transaction(function () use ($file, $type, $attachable, $uploadedBy, $metadata, $tenantId, $storedPath) {
            return Document::create([
                'tenant_id' => $tenantId,
                'patient_id' => $this->extractPatientId($attachable),
                'consultation_id' => $attachable instanceof Consultation ? $attachable->id : null,
                'ipd_admission_id' => $attachable instanceof IpdAdmission ? $attachable->id : null,
                'name' => $file->getClientOriginalName(),
                'type' => $type,
                'disk' => $this->storage->name(),
                'path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'metadata' => array_merge($metadata, [
                    'uploaded_via' => 'web',
                    'original_extension' => $file->getClientOriginalExtension(),
                ]),
                'uploaded_by' => $uploadedBy ?? auth()->id(),
            ]);
        });
    }

    /**
     * Generate a temporary signed URL for private document access.
     */
    public function temporaryUrl(Document $document, \DateTimeInterface $expiry): string
    {
        return $this->storage->temporaryUrl($document->path, $expiry);
    }

    /**
     * Delete a document and its file.
     */
    public function delete(Document $document): bool
    {
        $deleted = $this->storage->delete($document->path);

        if ($deleted) {
            $document->delete();
        }

        return $deleted;
    }

    /**
     * Build a unique storage path for the file.
     */
    private function buildPath(UploadedFile $file, ?int $tenantId, ?Model $attachable): string
    {
        $segments = [
            'tenants',
            $tenantId ?? 'global',
            'documents',
            $attachable?->getMorphClass() ?? 'general',
            $attachable?->id ?? 'unattached',
            now()->format('Y/m'),
            Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
        ];

        return implode('/', $segments);
    }

    private function extractPatientId(?Model $attachable): ?int
    {
        if ($attachable === null) {
            return null;
        }

        // If the attachable is a Patient, return its ID.
        if ($attachable instanceof Patient) {
            return $attachable->id;
        }

        // If the attachable has a patient_id, use it.
        if (isset($attachable->patient_id)) {
            return $attachable->patient_id;
        }

        return null;
    }
}
