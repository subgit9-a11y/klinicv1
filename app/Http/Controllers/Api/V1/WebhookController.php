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
            $request->headers->get('X-Cf-Signature')
            ?? $request->headers->get('X-Cashfree-Signature')
            ?? $request->headers->get('x-cf-signature')
            ?? ''
        );

        $result = $this->processor->process($payload, $signature);

        // Always 200 to prevent Cashfree from retrying a genuinely processed event.
        return response()->json($result, 200);
    }
}
