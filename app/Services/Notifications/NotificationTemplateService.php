<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for notification templates. Tenant-specific templates override the
 * global (tenant_id = null) defaults. Only Super Admin can edit global
 * templates; CLINIC_OWNER manages tenant overrides.
 */
class NotificationTemplateService
{
    private const CHANNELS = ['in_app', 'whatsapp', 'sms', 'email'];

    /**
     * @return Collection<int, NotificationTemplate>
     */
    public function list(bool $includeGlobal = true): Collection
    {
        $query = NotificationTemplate::query();

        if ($includeGlobal) {
            // Merge tenant-scoped (via global scope) + global templates.
            $query = NotificationTemplate::withoutGlobalScope('tenant')->orderBy('event_key')->orderBy('channel');
        }

        return $query->get();
    }

    public function find(int $id): ?NotificationTemplate
    {
        return NotificationTemplate::withoutGlobalScope('tenant')->find($id);
    }

    /**
     * @param  array{event_key:string, channel:string, name:string, subject?:?string, body:string, whatsapp_template_name?:?string, sms_template_id?:?string, is_active?:bool, is_global?:bool}  $attributes
     */
    public function create(array $attributes): NotificationTemplate
    {
        $validated = Validator::validate($attributes, [
            'event_key' => ['required', 'string', 'max:80'],
            'channel' => ['required', 'in:'.implode(',', self::CHANNELS)],
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:120'],
            'sms_template_id' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'is_global' => ['nullable', 'boolean'],
        ]);

        $isGlobal = (bool) ($validated['is_global'] ?? false);
        $tenantId = app(TenantContext::class)->id();

        if ($isGlobal && $tenantId !== null) {
            throw ValidationException::withMessages([
                'is_global' => 'Only Super Admin can create global templates.',
            ]);
        }

        return DB::transaction(function () use ($validated, $isGlobal) {
            // Enforce one active template per event_key+channel per scope.
            $existing = NotificationTemplate::withoutGlobalScope('tenant')
                ->where('event_key', $validated['event_key'])
                ->where('channel', $validated['channel'])
                ->where('tenant_id', $isGlobal ? null : app(TenantContext::class)->id())
                ->exists();
            if ($existing) {
                throw ValidationException::withMessages([
                    'event_key' => 'A template for this event/channel already exists in this scope.',
                ]);
            }

            return NotificationTemplate::create([
                'event_key' => $validated['event_key'],
                'channel' => $validated['channel'],
                'name' => $validated['name'],
                'subject' => $validated['subject'] ?? null,
                'body' => $validated['body'],
                'whatsapp_template_name' => $validated['whatsapp_template_name'] ?? null,
                'sms_template_id' => $validated['sms_template_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'tenant_id' => $isGlobal ? null : app(TenantContext::class)->id(),
            ]);
        });
    }

    public function update(NotificationTemplate $template, array $attributes): NotificationTemplate
    {
        $this->assertSameScope($template);

        $validated = Validator::make($attributes, [
            'name' => ['sometimes', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['sometimes', 'string'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:120'],
            'sms_template_id' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template->update($validated->validated());

        return $template->refresh();
    }

    public function delete(NotificationTemplate $template): void
    {
        $this->assertSameScope($template);
        $template->delete();
    }

    private function assertSameScope(NotificationTemplate $template): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            // Super Admin context — can edit any template.
            return;
        }
        if ($template->tenant_id !== null && $template->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'template' => 'Template belongs to a different tenant.',
            ]);
        }
        if ($template->tenant_id === null) {
            throw ValidationException::withMessages([
                'template' => 'Only Super Admin can modify global templates.',
            ]);
        }
    }
}
