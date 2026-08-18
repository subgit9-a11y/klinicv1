<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\EmailProviderInterface;
use App\Contracts\SmsProviderInterface;
use App\Contracts\WhatsAppProviderInterface;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Notifications\GenericInAppNotification;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Central notification dispatch engine.
 *
 * Resolves the active template for a given event_key + channel (tenant-
 * specific override falls back to global system template), renders the
 * body with variable substitution, and dispatches via the appropriate
 * provider. Each delivery is recorded in notification_deliveries for
 * audit and retry.
 *
 * Channels: in_app, whatsapp, sms, email.
 *
 * Providers gracefully degrade when not configured — the delivery is
 * recorded as FAILED with a descriptive error, but the app continues
 * to function.
 */
class NotificationService
{
    public function __construct(
        private readonly WhatsAppProviderInterface $whatsapp,
        private readonly SmsProviderInterface $sms,
        private readonly EmailProviderInterface $email,
    ) {}

    /**
     * Send a notification across one or more channels.
     *
     * @param  array<string, mixed>  $variables
     * @param  list<string>  $channels
     */
    public function send(
        Model $notifiable,
        string $eventKey,
        array $variables = [],
        array $channels = ['in_app']
    ): array {
        $results = [];

        foreach ($channels as $channel) {
            $results[$channel] = $this->sendOnChannel($notifiable, $eventKey, $channel, $variables);
        }

        return $results;
    }

    /**
     * Send on a single channel.
     *
     * @param  array<string, mixed>  $variables
     */
    public function sendOnChannel(
        Model $notifiable,
        string $eventKey,
        string $channel,
        array $variables
    ): NotificationDelivery {
        $template = $this->resolveTemplate($eventKey, $channel);

        $delivery = NotificationDelivery::create([
            'notification_template_id' => $template?->id,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->id,
            'channel' => $channel,
            'event_id' => $eventKey,
            'recipient' => $this->resolveRecipient($notifiable, $channel),
            'status' => 'PENDING',
            'attempts' => 0,
        ]);

        if ($template === null) {
            $delivery->update(['status' => 'FAILED', 'error' => "No template for {$eventKey}/{$channel}"]);

            return $delivery->refresh();
        }

        $body = $this->render($template->body, $variables);
        $subject = $this->render($template->subject, $variables);

        try {
            $result = match ($channel) {
                'in_app' => $this->sendInApp($notifiable, $subject, $body),
                'whatsapp' => $this->whatsapp->send(
                    $delivery->recipient ?? '',
                    $template->whatsapp_template_name ?? $eventKey,
                    $variables
                ),
                'sms' => $this->sms->send(
                    $delivery->recipient ?? '',
                    $body ?? '',
                    $variables
                ),
                'email' => $this->email->send(
                    $delivery->recipient ?? '',
                    $subject ?? $template->name,
                    $body ?? '',
                    $variables
                ),
                default => ['success' => false, 'reference' => null, 'message' => "Unknown channel: {$channel}"],
            };

            $delivery->update([
                'status' => $result['success'] ? 'SENT' : 'FAILED',
                'provider_reference' => $result['reference'] ?? null,
                'error' => $result['success'] ? null : $result['message'],
                'attempts' => 1,
                'sent_at' => $result['success'] ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Notification dispatch failed', [
                'event' => $eventKey,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            $delivery->update([
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'attempts' => 1,
            ]);
        }

        return $delivery->refresh();
    }

    /**
     * Resolve the active template: tenant-specific first, then global.
     */
    private function resolveTemplate(string $eventKey, string $channel): ?NotificationTemplate
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId !== null) {
            $template = NotificationTemplate::where('tenant_id', $tenantId)
                ->where('event_key', $eventKey)
                ->where('channel', $channel)
                ->where('is_active', true)
                ->first();
            if ($template !== null) {
                return $template;
            }
        }

        // Global templates have tenant_id = null. Must bypass the tenant
        // global scope to query them.
        return NotificationTemplate::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Render a template string by substituting {{variables}}.
     */
    private function render(?string $template, array $variables): ?string
    {
        if ($template === null) {
            return null;
        }

        foreach ($variables as $key => $value) {
            $template = str_replace('{{'.$key.'}}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * @return array{success: bool, reference: ?string, message: string}
     */
    private function sendInApp(Model $notifiable, ?string $subject, ?string $body): array
    {
        // In-app notifications are stored in the Laravel notifications table.
        // The notifiable model should use the Notifiable trait.
        if (method_exists($notifiable, 'notify')) {
            $notifiable->notify(new GenericInAppNotification(
                $subject ?? '',
                $body ?? ''
            ));
        }

        return ['success' => true, 'reference' => Str::uuid()->toString(), 'message' => 'In-app notification stored'];
    }

    private function resolveRecipient(Model $notifiable, string $channel): ?string
    {
        return match ($channel) {
            'whatsapp', 'sms' => $notifiable->phone ?? null,
            'email' => $notifiable->email ?? null,
            'in_app' => $notifiable->getMorphClass().':'.$notifiable->id,
            default => null,
        };
    }

    private function currentTenantId(): ?int
    {
        $context = app(TenantContext::class);
        $id = $context->id();

        return $id;
    }
}
