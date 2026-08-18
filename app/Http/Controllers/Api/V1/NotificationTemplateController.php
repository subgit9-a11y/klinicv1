<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreNotificationTemplateRequest;
use App\Http\Resources\Api\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Notification Templates
 */
class NotificationTemplateController extends Controller
{
    public function __construct(private readonly NotificationTemplateService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', NotificationTemplate::class);

        $templates = $this->service->list(includeGlobal: (bool) $request->query('include_global', true));

        return NotificationTemplateResource::collection($templates);
    }

    public function show(NotificationTemplate $notificationTemplate): Response
    {
        $this->authorize('view', $notificationTemplate);

        return response(NotificationTemplateResource::make($notificationTemplate));
    }

    public function store(StoreNotificationTemplateRequest $request): Response
    {
        $this->authorize('create', NotificationTemplate::class);

        $template = $this->service->create($request->validated());

        return response(NotificationTemplateResource::make($template), 201);
    }

    public function update(StoreNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): Response
    {
        $this->authorize('update', $notificationTemplate);

        $template = $this->service->update($notificationTemplate, $request->validated());

        return response(NotificationTemplateResource::make($template));
    }

    public function destroy(NotificationTemplate $notificationTemplate): Response
    {
        $this->authorize('delete', $notificationTemplate);

        $this->service->delete($notificationTemplate);

        return response()->noContent();
    }
}
