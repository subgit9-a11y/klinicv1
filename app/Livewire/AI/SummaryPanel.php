<?php

declare(strict_types=1);

namespace App\Livewire\AI;

use App\Models\AiRequest;
use App\Models\Document;
use App\Models\Investigation;
use App\Models\IpdAdmission;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Services\AI\AIManager;
use App\Services\AI\AiSummaryService;
use App\Services\Auth\Permissions;
use Livewire\Component;

/**
 * Reusable AI summary panel. Mounted on a clinical record (patient,
 * investigation, document, treatment plan, IPD admission) with the set of
 * summary features that apply. Generated output is always a DRAFT —
 * a practitioner must approve it before it is treated as official.
 */
class SummaryPanel extends Component
{
    /** Morph alias: patient|investigation|document|treatment_plan|ipd_admission */
    public string $contextType;

    public int $contextId;

    /** @var array<string, string> feature key => button label */
    public array $features = [];

    public ?int $generatedId = null;

    public function mount(string $contextType, int $contextId, array $features): void
    {
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->features = $features;
    }

    public function generate(string $featureKey, AiSummaryService $summaries): void
    {
        $this->guard();
        abort_unless(isset($this->features[$featureKey]), 404);

        $context = $this->resolveContext();

        $request = match ($featureKey) {
            'patient_summary' => $summaries->summarizePatient($context),
            'followup_assistant' => $summaries->followupAssistant($context),
            'lab_summary' => $summaries->summarizeInvestigation($context),
            'document_summary' => $summaries->summarizeDocument($context),
            'treatment_summary' => $summaries->summarizeTreatmentPlan($context),
            'ipd_summary' => $summaries->summarizeAdmission($context),
            default => abort(404),
        };

        $this->generatedId = $request->id;

        session()->flash('message', $request->output_status === 'DRAFT'
            ? 'AI draft generated — review and approve.'
            : 'AI generation failed: '.($request->error ?? 'unknown error'));
    }

    public function approve(int $id, AIManager $ai): void
    {
        $this->guard();

        $request = AiRequest::findOrFail($id); // tenant-scoped
        $ai->approve($request, auth()->id());

        session()->flash('message', 'AI summary approved.');
    }

    public function reject(int $id, AIManager $ai): void
    {
        $this->guard();

        $ai->reject(AiRequest::findOrFail($id), 'Rejected from summary panel');

        session()->flash('message', 'AI summary rejected.');
    }

    private function resolveContext(): Patient|Investigation|Document|TreatmentPlan|IpdAdmission
    {
        // All these models use BelongsToTenant — findOrFail is tenant-scoped.
        return match ($this->contextType) {
            'patient' => Patient::findOrFail($this->contextId),
            'investigation' => Investigation::findOrFail($this->contextId),
            'document' => Document::findOrFail($this->contextId),
            'treatment_plan' => TreatmentPlan::findOrFail($this->contextId),
            'ipd_admission' => IpdAdmission::findOrFail($this->contextId),
            default => abort(404),
        };
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::AI_USE), 403);
    }

    public function render()
    {
        $this->guard();

        $requests = AiRequest::where('contextable_type', $this->resolveMorphClass())
            ->where('contextable_id', $this->contextId)
            ->whereIn('ai_feature_id', function ($q) {
                $q->select('id')->from('ai_features')->whereIn('key', array_keys($this->features));
            })
            ->latest()
            ->limit(5)
            ->get();

        return view('livewire.ai.summary-panel', ['requests' => $requests]);
    }

    private function resolveMorphClass(): string
    {
        return match ($this->contextType) {
            'patient' => (new Patient)->getMorphClass(),
            'investigation' => (new Investigation)->getMorphClass(),
            'document' => (new Document)->getMorphClass(),
            'treatment_plan' => (new TreatmentPlan)->getMorphClass(),
            'ipd_admission' => (new IpdAdmission)->getMorphClass(),
            default => $this->contextType,
        };
    }
}
