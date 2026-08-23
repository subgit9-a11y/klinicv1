<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationTemplateService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Notification template editor: clinic owners manage their own tenant's
 * templates; platform (global) templates are read-only to them.
 */
class TemplateManager extends Component
{
    public string $filterChannel = '';
    public string $filterEvent = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $event_key = '';
    public string $channel = 'in_app';
    public string $name = '';
    public ?string $subject = null;
    public string $body = '';
    public ?string $whatsapp_template_name = null;
    public ?string $sms_template_id = null;
    public bool $is_active = true;

    public function startCreate(): void
    {
        $this->guard();
        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $id, NotificationTemplateService $templates): void
    {
        $this->guard();

        $template = $templates->find($id);
        abort_unless($template, 404);
        $this->assertEditable($template);

        $this->editingId = $template->id;
        $this->event_key = $template->event_key;
        $this->channel = $template->channel;
        $this->name = $template->name;
        $this->subject = $template->subject;
        $this->body = $template->body;
        $this->whatsapp_template_name = $template->whatsapp_template_name;
        $this->sms_template_id = $template->sms_template_id;
        $this->is_active = (bool) $template->is_active;
        $this->showForm = true;
    }

    public function save(NotificationTemplateService $templates): void
    {
        $this->guard();

        $data = $this->validate([
            'event_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'],
            'channel' => ['required', 'in:in_app,whatsapp,sms,email'],
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:120'],
            'sms_template_id' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $template = $templates->find($this->editingId);
            abort_unless($template, 404);
            $templates->update($template, $data);
            session()->flash('message', 'Template updated.');
        } else {
            $data['is_global'] = auth()->user()->isSuperAdmin();
            $templates->create($data);
            session()->flash('message', 'Template created.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function remove(int $id, NotificationTemplateService $templates): void
    {
        $this->guard();

        $template = $templates->find($id);
        abort_unless($template, 404);

        try {
            $templates->delete($template);
            session()->flash('message', 'Template deleted.');
        } catch (ValidationException $e) {
            $this->addError('template', $e->validator->errors()->first());
        }
    }

    public function render(NotificationTemplateService $templates)
    {
        $this->guard();

        $all = $templates->list(true)
            ->when($this->filterChannel !== '', fn ($c) => $c->where('channel', $this->filterChannel))
            ->when($this->filterEvent !== '', fn ($c) => $c->filter(fn ($t) => str_contains($t->event_key, $this->filterEvent)));

        $tenantId = auth()->user()->tenant_id;

        return view('livewire.notifications.template-manager', [
            'templates' => $all->values(),
            'channels' => ['in_app', 'whatsapp', 'sms', 'email'],
            'currentTenantId' => $tenantId,
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'event_key', 'channel', 'name', 'subject', 'body', 'whatsapp_template_name', 'sms_template_id', 'is_active']);
        $this->channel = 'in_app';
        $this->is_active = true;
    }

    private function assertEditable(NotificationTemplate $template): void
    {
        $user = auth()->user();
        abort_if($template->tenant_id === null && ! $user->isSuperAdmin(), 403);
        abort_if($template->tenant_id !== null && $template->tenant_id !== $user->tenant_id, 403);
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && (auth()->user()->isClinicOwner() || auth()->user()->isSuperAdmin()), 403);
    }
}
