<?php

declare(strict_types=1);

namespace App\Livewire\SuperAdmin;

use App\Contracts\OCRProviderInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SpeechProviderInterface;
use App\Contracts\StorageProviderInterface;
use App\Contracts\VideoProviderInterface;
use App\Services\AI\GeminiProvider;
use App\Services\Notifications\Providers\MetaWhatsAppProvider;
use App\Services\Notifications\Providers\Msg91SmsProvider;
use App\Services\Notifications\Providers\ResendEmailProvider;
use Livewire\Component;

/**
 * Super Admin read-only integration overview: which external providers are
 * configured and usable. Never exposes credentials — only configured
 * yes/no and provider names, sourced from each provider's isConfigured().
 */
class IntegrationStatus extends Component
{
    public string $accountProvider = 'cashfree';

    public ?int $accountTenantId = null;

    public string $accountName = '';

    /** @var array<string, string> */
    public array $credentials = [];

    public function saveAccount(\App\Services\Integrations\IntegrationAccountService $accounts): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);

        $this->validate([
            'accountProvider' => ['required', 'in:'.implode(',', array_keys(\App\Services\Integrations\IntegrationAccountService::PROVIDERS))],
            'accountTenantId' => ['nullable', 'integer', 'exists:tenants,id'],
            'credentials' => ['required', 'array', 'min:1'],
            'credentials.*' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $accounts->upsert(
                $this->accountProvider,
                $this->credentials,
                $this->accountTenantId,
                $this->accountName !== '' ? $this->accountName : null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('credentials', $e->validator->errors()->first());

            return;
        }

        $this->reset(['accountName', 'credentials']);
        session()->flash('message', 'Integration account saved.');
    }

    public function toggleAccount(int $accountId, \App\Services\Integrations\IntegrationAccountService $accounts): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);
        $accounts->toggle(\App\Models\IntegrationAccount::withoutGlobalScopes()->findOrFail($accountId));
    }

    public function deleteAccount(int $accountId, \App\Services\Integrations\IntegrationAccountService $accounts): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);
        $accounts->delete(\App\Models\IntegrationAccount::withoutGlobalScopes()->findOrFail($accountId));
        session()->flash('message', 'Integration account deleted.');
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403);

        $integrations = [
            [
                'name' => 'Cashfree Payments',
                'category' => 'Payments',
                'provider' => app(PaymentGatewayInterface::class)->name(),
                'configured' => app(PaymentGatewayInterface::class)->isConfigured(),
                'env' => 'CASHFREE_APP_ID / CASHFREE_SECRET_KEY',
            ],
            [
                'name' => 'Gemini AI',
                'category' => 'AI',
                'provider' => app(GeminiProvider::class)->name(),
                'configured' => app(GeminiProvider::class)->isConfigured(),
                'env' => 'GEMINI_API_KEY',
            ],
            [
                'name' => 'WhatsApp (Meta)',
                'category' => 'Notifications',
                'provider' => app(MetaWhatsAppProvider::class)->name(),
                'configured' => app(MetaWhatsAppProvider::class)->isConfigured(),
                'env' => 'META_WHATSAPP_TOKEN / META_WHATSAPP_PHONE_NUMBER_ID',
            ],
            [
                'name' => 'SMS (MSG91)',
                'category' => 'Notifications',
                'provider' => app(Msg91SmsProvider::class)->name(),
                'configured' => app(Msg91SmsProvider::class)->isConfigured(),
                'env' => 'MSG91_AUTH_KEY / MSG91_SENDER_ID',
            ],
            [
                'name' => 'Email (Resend)',
                'category' => 'Notifications',
                'provider' => app(ResendEmailProvider::class)->name(),
                'configured' => app(ResendEmailProvider::class)->isConfigured(),
                'env' => 'RESEND_API_KEY / MAIL_FROM_ADDRESS',
            ],
            [
                'name' => 'Google Meet (Teleconsultation)',
                'category' => 'Video',
                'provider' => app(VideoProviderInterface::class)->name(),
                'configured' => app(VideoProviderInterface::class)->isConfigured(),
                'env' => 'GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET',
            ],
            [
                'name' => 'OCR',
                'category' => 'Documents',
                'provider' => app(OCRProviderInterface::class)->name(),
                'configured' => app(OCRProviderInterface::class)->isConfigured(),
                'env' => 'OCR_PROVIDER + GOOGLE_VISION_API_KEY or TESSERACT_BINARY',
            ],
            [
                'name' => 'Speech-to-Text',
                'category' => 'Documents',
                'provider' => app(SpeechProviderInterface::class)->name(),
                'configured' => app(SpeechProviderInterface::class)->isConfigured(),
                'env' => 'SPEECH_PROVIDER + OPENAI_API_KEY or WHISPER_CPP_*',
            ],
            [
                'name' => 'Storage (S3)',
                'category' => 'Infrastructure',
                'provider' => app(StorageProviderInterface::class)->name(),
                'configured' => app(StorageProviderInterface::class)->isConfigured(),
                'env' => 'AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_BUCKET',
            ],
        ];

        $accountService = app(\App\Services\Integrations\IntegrationAccountService::class);
        $managedAccounts = \App\Models\IntegrationAccount::withoutGlobalScopes()
            ->orderBy('provider')
            ->get()
            ->map(fn ($a) => [
                'account' => $a,
                'masked' => $accountService->masked($a),
                'tenant_name' => $a->tenant_id ? \App\Models\Tenant::find($a->tenant_id)?->name : 'All clinics (global)',
            ]);

        return view('livewire.super-admin.integration-status', [
            'integrations' => $integrations,
            'managedAccounts' => $managedAccounts,
            'providerKeys' => \App\Services\Integrations\IntegrationAccountService::PROVIDERS,
            'tenants' => \App\Models\Tenant::orderBy('name')->get(['id', 'name']),
        ])->layout('components.layouts.app');
    }
}
