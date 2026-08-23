<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Patient;
use App\Services\Audit\AuditService;
use App\Services\Auth\Permissions;
use App\Services\Documents\DocumentService;
use App\Services\Documents\OcrService;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Document lifecycle manager: upload, categorize, version, OCR, share
 * (signed URL), archive and delete. Every mutation is audit-logged.
 * Gated by documents.* permissions.
 */
class DocumentManager extends Component
{
    use WithFileUploads;

    private const TYPES = ['LAB_REPORT', 'IMAGING', 'PRESCRIPTION', 'DISCHARGE_SUMMARY', 'CONSENT', 'REFERRAL', 'CLINICAL', 'RECEIPT', 'INVOICE', 'OTHER'];

    public string $search = '';

    public string $typeFilter = '';

    public bool $showArchived = false;

    public bool $showForm = false;

    // Upload form
    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public string $type = 'OTHER';

    public ?int $patient_id = null;

    public string $patientSearch = '';

    // Detail / version / share state
    public ?int $detailId = null;

    /** @var TemporaryUploadedFile|null */
    public $versionFile = null;

    public ?string $shareUrl = null;

    public ?int $shareDocId = null;

    private AuditService $audit;

    public function boot(AuditService $audit): void
    {
        $this->audit = $audit;
    }

    public function upload(DocumentService $documents): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_UPLOAD), 403);

        $this->validate([
            'file' => 'required|file|max:20480',
            'type' => 'required|in:'.implode(',', self::TYPES),
            'patient_id' => 'nullable|exists:patients,id',
        ]);

        $patient = $this->patient_id !== null ? Patient::findOrFail($this->patient_id) : null;

        $document = $documents->upload($this->file, $this->type, $patient, auth()->id(), ['version' => 1]);

        $this->audit->record('document.uploaded', 'documents', ['after' => [
            'name' => $document->name, 'type' => $document->type,
        ]], $document);

        session()->flash('message', 'Document uploaded.');
        $this->reset(['file', 'patient_id', 'patientSearch']);
        $this->type = 'OTHER';
        $this->showForm = false;
    }

    public function recategorize(int $id, string $type): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_UPLOAD), 403);

        if (! in_array($type, self::TYPES, true)) {
            return;
        }

        $document = Document::findOrFail($id); // tenant-scoped
        $before = $document->type;
        $document->update(['type' => $type]);

        $this->audit->record('document.recategorized', 'documents', [
            'before' => ['type' => $before], 'after' => ['type' => $type],
        ], $document);

        session()->flash('message', 'Document recategorized.');
    }

    public function uploadNewVersion(int $id, DocumentService $documents): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_UPLOAD), 403);

        $this->validate(['versionFile' => 'required|file|max:20480']);

        $original = Document::findOrFail($id); // tenant-scoped
        $rootId = $original->metadata['version_of'] ?? $original->id;

        // Version computed in PHP — portable across SQLite and MySQL.
        $nextVersion = $this->versionsOf($rootId)
            ->map(fn (Document $d) => (int) ($d->metadata['version'] ?? 1))
            ->max() + 1;

        $document = $documents->upload(
            $this->versionFile,
            $original->type,
            $original->patient,
            auth()->id(),
            ['version' => $nextVersion, 'version_of' => $rootId, 'supersedes' => $original->id],
        );

        $this->audit->record('document.version_uploaded', 'documents', ['after' => [
            'version' => $nextVersion, 'supersedes' => $original->id,
        ]], $document);

        session()->flash('message', "Version {$nextVersion} uploaded.");
        $this->reset('versionFile');
    }

    public function runOcr(int $id, OcrService $ocr): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_VIEW), 403);

        $result = $ocr->extractFromDocument(Document::findOrFail($id));

        session()->flash('message', $result['success'] ? 'OCR extraction complete.' : 'OCR failed: '.$result['message']);
    }

    public function share(int $id): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_VIEW), 403);

        $document = Document::findOrFail($id); // tenant-scoped

        $this->shareUrl = URL::temporarySignedRoute(
            'documents.stream',
            now()->addHours(24),
            ['path' => base64_encode($document->path)],
        );
        $this->shareDocId = $document->id;

        $this->audit->record('document.shared', 'documents', ['after' => [
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ]], $document);
    }

    public function archive(int $id): void
    {
        $this->setArchived($id, true);
    }

    public function unarchive(int $id): void
    {
        $this->setArchived($id, false);
    }

    private function setArchived(int $id, bool $archived): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_UPLOAD), 403);

        $document = Document::findOrFail($id); // tenant-scoped
        $metadata = $document->metadata ?? [];
        if ($archived) {
            $metadata['archived_at'] = now()->toIso8601String();
        } else {
            unset($metadata['archived_at']);
        }
        $document->update(['metadata' => $metadata]);

        $this->audit->record($archived ? 'document.archived' : 'document.unarchived', 'documents', [], $document);

        session()->flash('message', $archived ? 'Document archived.' : 'Document restored.');
    }

    public function delete(int $id, DocumentService $documents): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_DELETE), 403);

        $document = Document::findOrFail($id); // tenant-scoped
        $type = $document->type;
        $name = $document->name;

        $documents->delete($document);

        $this->audit->record('document.deleted', 'documents', ['before' => [
            'name' => $name, 'type' => $type,
        ]]);

        session()->flash('message', 'Document deleted.');
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $this->detailId === $id ? null : $id;
        $this->reset(['versionFile', 'shareUrl', 'shareDocId']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    private function versionsOf(int $rootId): \Illuminate\Database\Eloquent\Collection
    {
        return Document::where('id', $rootId)
            ->orWhere('metadata->version_of', $rootId)
            ->orderBy('id')
            ->get(['id', 'name', 'metadata', 'created_at']);
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::DOCUMENTS_VIEW), 403);

        $items = Document::with(['patient:id,first_name,last_name,k360_uid', 'uploadedBy:id,name'])
            ->when(! $this->showArchived, fn ($q) => $q->where(function ($q2) {
                $q2->whereNull('metadata')->orWhereNull('metadata->archived_at');
            }))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->search !== '', fn ($q) => $q->where(function ($q2) {
                $term = "%{$this->search}%";
                $q2->where('name', 'like', $term)
                    ->orWhere('metadata->ocr_text', 'like', $term);
            }))
            ->latest()
            ->limit(100)
            ->get();

        $detail = null;
        $auditTrail = collect();
        $versions = collect();
        if ($this->detailId !== null) {
            $detail = Document::with('patient:id,first_name,last_name,k360_uid')->find($this->detailId);
            if ($detail !== null) {
                $versions = $this->versionsOf($detail->metadata['version_of'] ?? $detail->id);
                // AuditLog is a global (unscoped) table — restrict to the current tenant.
                $auditTrail = AuditLog::where('auditable_type', (new Document)->getMorphClass())
                    ->where('auditable_id', $detail->id)
                    ->when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('tenant_id', $detail->tenant_id))
                    ->latest()
                    ->limit(20)
                    ->get();
            }
        }

        return view('livewire.documents.document-manager', [
            'items' => $items,
            'detail' => $detail,
            'versions' => $versions,
            'auditTrail' => $auditTrail,
            'types' => self::TYPES,
            'ocrConfigured' => app(OcrService::class)->isConfigured(),
            'patients' => $this->patientSearch !== ''
                ? Patient::where('first_name', 'like', "%{$this->patientSearch}%")
                    ->orWhere('last_name', 'like', "%{$this->patientSearch}%")
                    ->orWhere('k360_uid', 'like', "%{$this->patientSearch}%")
                    ->limit(10)->get(['id', 'first_name', 'last_name', 'k360_uid'])
                : collect(),
        ])->layout('components.layouts.app');
    }
}
