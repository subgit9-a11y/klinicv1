<?php

declare(strict_types=1);

namespace App\Livewire\EMR;

use App\Models\Document;
use App\Models\Investigation;
use App\Models\InvestigationResult;
use App\Models\Patient;
use App\Services\Auth\Permissions;
use App\Services\Documents\DocumentService;
use App\Services\Documents\OcrService;
use App\Services\EMR\InvestigationService;
use App\Services\Tenancy\TenantContext;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Lab / investigation workflow: order → start → upload report (+OCR) →
 * record structured results → doctor review → complete / cancel.
 * Backed by InvestigationService, DocumentService and OcrService;
 * gated by consultations.* / documents.upload permissions.
 */
class InvestigationBoard extends Component
{
    use WithFileUploads;

    private const CATEGORIES = ['LAB', 'RADIOLOGY', 'CARDIAC', 'PATHOLOGY', 'OTHER'];

    public string $statusFilter = '';

    public string $categoryFilter = '';

    public bool $showForm = false;

    // Order form
    public ?int $patient_id = null;

    public string $name = '';

    public string $category = 'LAB';

    public string $patientSearch = '';

    // Detail panel
    public ?int $detailId = null;

    // Result entry form
    public string $resultParameter = '';

    public string $resultValue = '';

    public string $resultUnit = '';

    public string $resultRange = '';

    public string $resultFlag = 'NORMAL';

    public ?int $resultDocumentId = null;

    /** @var TemporaryUploadedFile|null */
    public $reportFile = null;

    public function order(InvestigationService $investigations): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::CONSULTATIONS_CREATE), 403);

        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'name' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', self::CATEGORIES),
        ]);

        $investigations->order([
            'patient_id' => $this->patient_id,
            'name' => $this->name,
            'category' => $this->category,
        ]);

        session()->flash('message', 'Investigation ordered.');
        $this->reset(['patient_id', 'name', 'patientSearch']);
        $this->category = 'LAB';
        $this->showForm = false;
    }

    public function start(int $id, InvestigationService $investigations): void
    {
        $this->transition($id, ['status' => 'IN_PROGRESS'], $investigations, 'Investigation started.');
    }

    public function complete(int $id, InvestigationService $investigations): void
    {
        $this->transition($id, ['status' => 'COMPLETED'], $investigations, 'Investigation completed.');
    }

    public function cancel(int $id, InvestigationService $investigations): void
    {
        $this->transition($id, ['status' => 'CANCELLED'], $investigations, 'Investigation cancelled.');
    }

    private function transition(int $id, array $attributes, InvestigationService $investigations, string $flash): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::CONSULTATIONS_EDIT), 403);

        $investigations->update(Investigation::findOrFail($id), $attributes);
        session()->flash('message', $flash);
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $this->detailId === $id ? null : $id;
        $this->reset(['resultParameter', 'resultValue', 'resultUnit', 'resultRange', 'resultDocumentId', 'reportFile']);
        $this->resultFlag = 'NORMAL';
    }

    public function addResult(int $id): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::CONSULTATIONS_EDIT), 403);

        $this->validate([
            'resultParameter' => 'required|string|max:255',
            'resultValue' => 'required|string|max:255',
            'resultUnit' => 'nullable|string|max:50',
            'resultRange' => 'nullable|string|max:100',
            'resultFlag' => 'required|in:NORMAL,HIGH,LOW,CRITICAL',
            'resultDocumentId' => 'nullable|exists:documents,id',
        ]);

        $investigation = Investigation::findOrFail($id); // tenant-scoped

        InvestigationResult::create([
            'tenant_id' => app(TenantContext::class)->id(),
            'investigation_id' => $investigation->id,
            'document_id' => $this->resultDocumentId,
            'parameter' => $this->resultParameter,
            'value' => $this->resultValue,
            'unit' => $this->resultUnit !== '' ? $this->resultUnit : null,
            'reference_range' => $this->resultRange !== '' ? $this->resultRange : null,
            'flag' => $this->resultFlag,
            'resulted_at' => now(),
        ]);

        session()->flash('message', 'Result recorded.');
        $this->reset(['resultParameter', 'resultValue', 'resultUnit', 'resultRange', 'resultDocumentId']);
        $this->resultFlag = 'NORMAL';
    }

    public function uploadReport(int $id, DocumentService $documents): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_UPLOAD), 403);

        $this->validate(['reportFile' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240']);

        $investigation = Investigation::findOrFail($id); // tenant-scoped

        $documents->upload(
            $this->reportFile,
            'LAB_REPORT',
            $investigation->patient,
            auth()->id(),
            ['investigation_id' => $investigation->id],
        );

        session()->flash('message', 'Report uploaded.');
        $this->reset('reportFile');
    }

    public function runOcr(int $documentId, OcrService $ocr): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::DOCUMENTS_VIEW), 403);

        $document = Document::findOrFail($documentId); // tenant-scoped
        $result = $ocr->extractFromDocument($document);

        session()->flash('message', $result['success'] ? 'OCR extraction complete.' : 'OCR failed: '.$result['message']);
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::CONSULTATIONS_VIEW), 403);

        $items = Investigation::with(['patient:id,first_name,last_name,k360_uid'])
            ->withCount('results')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category', $this->categoryFilter))
            ->orderByDesc('requested_at')
            ->limit(100)
            ->get();

        $detail = null;
        $detailDocuments = collect();
        if ($this->detailId !== null) {
            $detail = Investigation::with(['results.document:id,name', 'patient:id,first_name,last_name,k360_uid'])
                ->find($this->detailId);
            if ($detail !== null) {
                $detailDocuments = Document::where('metadata->investigation_id', $detail->id)
                    ->latest()
                    ->get(['id', 'name', 'mime_type', 'metadata', 'created_at']);
            }
        }

        return view('livewire.emr.investigation-board', [
            'items' => $items,
            'detail' => $detail,
            'detailDocuments' => $detailDocuments,
            'categories' => self::CATEGORIES,
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
