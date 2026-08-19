<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Cashfree payment webhooks and delegates to the
 * WebhookProcessor (signature verification, server-side payment
 * verification, idempotent payment recording, audit logging).
 *
 * This endpoint is NOT token-authenticated; it is protected by the
 * webhook signature and server-side order verification performed inside
 * the WebhookProcessor.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly WebhookProcessor $processor) {}

    public function payments(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $signature = (string) (
            $request->headers->get('x-webhook-signature')
            ?? $request->headers->get('X-Cf-Signature')
            ?? $request->headers->get('X-Cashfree-Signature')
            ?? $request->headers->get('x-cf-signature')
            ?? ''
        );
        $timestamp = $request->headers->get('x-webhook-timestamp');

        // Signature verification must run against the EXACT raw HTTP body —
        // never a re-encoding of the parsed JSON (whitespace/escaping surely
        // differs from Cashfree's original and would reject valid webhooks).
        $result = $this->processor->process($payload, $signature, $request->getContent(), $timestamp);

        // The processor returns a semantic status: 200 for processed/safely
        // idempotent events, 4xx for deterministic rejections (bad signature,
        // unknown order, amount mismatch). Non-2xx answers let Cashfree retry
        // races (unknown order) while operational monitoring can alert on
        // genuine failures instead of them being masked by a blanket 200.
        $status = $result['status'] ?? 200;
        unset($result['status']);

        return response()->json($result, $status);
    }
}
