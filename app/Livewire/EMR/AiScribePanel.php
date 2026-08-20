<?php

declare(strict_types=1);

namespace App\Livewire\EMR;

use App\Models\AiRequest;
use App\Models\Consultation;
use App\Models\Document;
use App\Services\AI\AiScribeService;
use App\Services\Auth\Permissions;
use App\Services\Documents\DocumentService;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * AI Scribe panel on a consultation: dictation (audio upload OR chief
 * complaint text) → AI SOAP draft → doctor edits → approve → write the
 * consultation. AI output is NEVER committed without doctor approval.
 */
class AiScribePanel extends Component
{
    use WithFileUploads;

    public Consultation $consultation;

    public ?int $aiRequestId = null;

    // Upload + transcribe path
    public ?UploadedFile $audio = null;

    // Editable SOAP draft fields
    public ?string $subjective = null;

    public ?string $objective = null;

    public ?string $assessment = null;

    public ?string $plan = null;

    public function mount(Consultation $consultation): void
    {
        $this->consultation = $consultation;
        $this->guard();

        // Load the latest DRAFT scribe request for this consultation, if any.
        $latest = AiRequest::where('contextable_type', Consultation::class)
            ->where('contextable_id', $consultation->id)
            ->where('output_status', 'DRAFT')
            ->latest()
            ->first();

        if ($latest !== null) {
            $this->aiRequestId = $latest->id;
            $this->loadOutput(app(AiScribeService::class)->parseOutput((string) $latest->output));
        }
    }

    public function scribeFromAudio(AiScribeService $scribe, DocumentService $documents): void
    {
        $this->guard();

        $this->validate(['audio' => 'required|file|mimes:mp3,wav,m4a,ogg|max:20480']);

        $document = $documents->upload($this->audio, 'DICTATION', $this->consultation);
        $request = $scribe->draftFromAudio($this->consultation, $document);

        $this->aiRequestId = $request->id;
        $this->loadOutput($scribe->parseOutput((string) $request->output));

        session()->flash('message', 'Draft generated — review and approve.');
    }

    public function scribeFromComplaint(AiScribeService $scribe): void
    {
        $this->guard();

        $request = $scribe->draftFromChiefComplaint($this->consultation);

        $this->aiRequestId = $request->id;
        $this->loadOutput($scribe->parseOutput((string) $request->output));

        session()->flash('message', 'Draft generated — review and approve.');
    }

    public function approve(AiScribeService $scribe): void
    {
        $this->guard();

        $this->validate([
            'subjective' => 'nullable|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
        ]);

        $request = AiRequest::findOrFail($this->aiRequestId);
        $scribe->approve($request, auth()->user(), [
            'subjective' => $this->subjective,
            'objective' => $this->objective,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
        ]);

        $this->consultation->refresh();
        session()->flash('message', 'Approved and written to the consultation.');
    }

    public function discard(AiScribeService $scribe): void
    {
        $this->guard();

        $request = AiRequest::findOrFail($this->aiRequestId);
        $scribe->ai->reject($request, 'Discarded by doctor');

        $this->reset(['aiRequestId', 'subjective', 'objective', 'assessment', 'plan']);
        session()->flash('message', 'Draft discarded.');
    }

    private function loadOutput(array $output): void
    {
        $this->subjective = $output['subjective'];
        $this->objective = $output['objective'];
        $this->assessment = $output['assessment'];
        $this->plan = $output['plan'];
    }

    private function guard(): void
    {
        abort_unless(
            auth()->check()
            && auth()->user()->hasPermission(Permissions::AI_USE)
            && auth()->user()->tenant_id === $this->consultation->tenant_id,
            403
        );
    }

    public function render()
    {
        $this->guard();

        return view('livewire.emr.ai-scribe-panel');
    }
}
