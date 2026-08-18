<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ActivateSubscriptionRequest;
use App\Http\Resources\Api\PlanResource;
use App\Http\Resources\Api\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Plans\PlanService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Plans & Subscriptions
 *
 * SaaS plan catalogue and tenant subscription lifecycle.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly PlanService $planService) {}

    public function indexPlans(): AnonymousResourceCollection
    {
        $this->authorize('viewAnyPlan', Subscription::class);

        return PlanResource::collection($this->planService->activePlans()->load('features'));
    }

    public function showPlan(Plan $plan): Response
    {
        $this->authorize('viewPlan', [Subscription::class, $plan]);

        return response(['data' => PlanResource::make($plan->load('features'))]);
    }

    public function currentSubscription(): Response
    {
        $this->authorize('viewAnySubscription', Subscription::class);

        $subscription = $this->planService->activeSubscription();

        if ($subscription === null) {
            return response(['data' => null, 'message' => 'No active subscription.'], 200);
        }

        $this->authorize('viewSubscription', $subscription);

        return response(['data' => SubscriptionResource::make($subscription->load('plan'))]);
    }

    public function activate(ActivateSubscriptionRequest $request): Response
    {
        $this->authorize('activate', Subscription::class);

        $subscription = $this->planService->activate(
            $request->user()->tenant_id,
            $request->validated('plan_code'),
        );

        return response([
            'message' => 'Subscription activated.',
            'data' => SubscriptionResource::make($subscription->load('plan')),
        ], 201);
    }

    public function cancel(Subscription $subscription): Response
    {
        $this->authorize('cancel', $subscription);

        $reason = (string) request()->input('reason', 'User requested');
        $this->planService->cancel($subscription, $reason);

        return response([
            'message' => 'Subscription cancelled.',
            'data' => SubscriptionResource::make($subscription->fresh()->load('plan')),
        ]);
    }
}
