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

        return view('livewire.super-admin.integration-status', [
            'integrations' => $integrations,
        ])->layout('components.layouts.app');
    }
}
