<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GenerateAiRequestRequest;
use App\Http\Resources\Api\AiRequestResource;
use App\Models\AiRequest;
use App\Services\AI\AIManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * @group AI Governance
 *
 * AI clinical-output governance flow. Every generation produces a DRAFT that
 * a practitioner must approve before it becomes official. Drafts may also be
 * rejected with a reason. All actions are audit-logged via AIManager.
 */
class AiRequestController extends Controller
{
    private const MORPH_ALIAS = [
        'patient' => \App\Models\Patient::class,
        'consultation' => \App\Models\Consultation::class,
        'ipd_admission' => \App\Models\IpdAdmission::class,
    ];

    public function __construct(private readonly AIManager $ai) {}

    /**
     * List AI requests (drafts awaiting review by default).
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AiRequest::class);

        $requests = AiRequest::query()
            ->when(request('status'), fn ($q, $status) => $q->where('output_status', $status))
            ->when(request('feature_key'), function ($q, $key) {
                $q->whereHas('feature', fn ($fq) => $fq->where('key', $key));
            })
            ->latest()
            ->paginate(25);

        return AiRequestResource::collection($requests);
    }

    public function show(AiRequest $aiRequest): Response
    {
        $this->authorize('view', $aiRequest);

        return response(['data' => AiRequestResource::make($aiRequest)]);
    }

    /**
     * Generate a new AI draft (always DRAFT — never auto-approved).
     */
    public function generate(GenerateAiRequestRequest $request): JsonResponse
    {
        $this->authorize('create', AiRequest::class);

        $contextable = $this->resolveContextable(
            $request->validated('contextable_type'),
            (int) $request->validated('contextable_id'),
        );

        abort_unless($contextable !== null, 422, 'Contextable model not found.');

        $aiRequest = $this->ai->generate(
            $request->validated('feature_key'),
            $contextable,
            $request->validated('variables', []),
            $request->validated('system'),
        );

        return response()->json(
            ['data' => AiRequestResource::make($aiRequest)],
            201,
        );
    }

    public function approve(AiRequest $aiRequest): Response
    {
        $this->authorize('approve', $aiRequest);

        $approved = $this->ai->approve($aiRequest, (int) request()->user()?->id);

        return response(['data' => AiRequestResource::make($approved)]);
    }

    public function reject(AiRequest $aiRequest): Response
    {
        $this->authorize('reject', $aiRequest);

        $reason = (string) request()->input('reason', '');
        $rejected = $this->ai->reject($aiRequest, $reason !== '' ? $reason : null);

        return response(['data' => AiRequestResource::make($rejected)]);
    }

    /**
     * Resolve the morph contextable model from the request. Accepts the
     * short alias (patient, consultation, ipd_admission) or the FQN.
     */
    private function resolveContextable(string $type, int $id): ?Model
    {
        $normalized = Str::lower($type);
        $class = self::MORPH_ALIAS[$normalized]
            ?? (class_exists($type) ? $type : null);

        if ($class === null) {
            return null;
        }

        /** @var Model $class */
        return $class::find($id);
    }
}
