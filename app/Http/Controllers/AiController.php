<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiRequest;
use App\Models\Patient;
use App\Services\AI\AIManager;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private readonly AIManager $ai)
    {
    }

    public function index(Request $request)
    {
        $query = AiRequest::query()->with('feature')->latest();

        if ($request->filled('status')) {
            $query->where('output_status', $request->input('status'));
        }

        $requests = $query->paginate(25);

        return view('ai.index', ['requests' => $requests]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'feature_key' => ['required', 'string'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'system' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);

        $aiRequest = $this->ai->generate(
            $validated['feature_key'],
            $patient,
            $validated['variables'] ?? [],
            $validated['system'] ?? null,
        );

        return redirect()
            ->route('ai.index')
            ->with('status', "AI draft {$aiRequest->output_status} (request #{$aiRequest->id}).");
    }

    public function approve(Request $request, AiRequest $aiRequest)
    {
        $this->ai->approve($aiRequest, $request->user()->id);

        return redirect()->route('ai.index')->with('status', "AI draft #{$aiRequest->id} approved.");
    }

    public function reject(Request $request, AiRequest $aiRequest)
    {
        $this->ai->reject($aiRequest, $request->input('reason'));

        return redirect()->route('ai.index')->with('status', "AI draft #{$aiRequest->id} rejected.");
    }
}
