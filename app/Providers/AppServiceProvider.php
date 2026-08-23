<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\EmailProviderInterface;
use App\Contracts\OCRProviderInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SmsProviderInterface;
use App\Contracts\SpeechProviderInterface;
use App\Contracts\StorageProviderInterface;
use App\Contracts\SubscriptionProviderInterface;
use App\Contracts\VideoProviderInterface;
use App\Contracts\WhatsAppProviderInterface;
use App\Integrations\OCR\GoogleVisionOcrProvider;
use App\Integrations\OCR\TesseractOcrProvider;
use App\Integrations\Speech\OpenAiWhisperProvider;
use App\Integrations\Speech\WhisperCppProvider;
use App\Models\IpdAdmission;
use App\Models\IpdBed;
use App\Models\IpdRoom;
use App\Models\IpdWard;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TreatmentBooking;
use App\Models\TreatmentRoom;
use App\Models\TreatmentService;
use App\Models\User;
use App\Policies\IpdConfigurationPolicy;
use App\Policies\IpdPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TreatmentCatalogPolicy;
use App\Policies\TreatmentPolicy;
use App\Services\AI\AIContextBuilder;
use App\Services\AI\AIManager;
use App\Services\AI\GeminiProvider;
use App\Services\Appointments\AppointmentService;
use App\Services\Auth\PermissionService;
use App\Services\Auth\TokenService;
use App\Services\Auth\TwoFactorService;
use App\Services\Billing\BillingService;
use App\Services\Billing\CashRegisterService;
use App\Services\Billing\ExpenseService;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\EMR\ConsultationService;
use App\Services\EMR\PrescriptionService;
use App\Services\EMR\VitalsService;
use App\Services\IPD\IpdService;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\NotificationTemplateService;
use App\Services\Notifications\Providers\MetaWhatsAppProvider;
use App\Services\Notifications\Providers\Msg91SmsProvider;
use App\Services\Notifications\Providers\ResendEmailProvider;
use App\Services\Patients\PatientService;
use App\Services\Patients\PatientUidService;
use App\Services\Payments\CashfreePaymentProvider;
use App\Services\Payments\CashfreeSubscriptionProvider;
use App\Services\Payments\WebhookProcessor;
use App\Services\Plans\FeatureService;
use App\Services\Plans\LimitService;
use App\Services\Plans\PlanService;
use App\Services\Queue\QueueService;
use App\Services\Reports\ReportService;
use App\Services\Storage\S3StorageProvider;
use App\Services\Telemedicine\TeleconsultationService;
use App\Services\Tenancy\TenantContext;
use App\Services\Treatments\TreatmentBookingService;
use App\Services\Video\GoogleMeetProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(PatientUidService::class);
        $this->app->singleton(PatientService::class);
        $this->app->singleton(AppointmentService::class);
        $this->app->singleton(QueueService::class);
        $this->app->singleton(ConsultationService::class);
        $this->app->singleton(PrescriptionService::class);
        $this->app->singleton(VitalsService::class);
        $this->app->singleton(PlanService::class);
        $this->app->singleton(FeatureService::class);
        $this->app->singleton(LimitService::class);
        $this->app->singleton(TreatmentBookingService::class);
        $this->app->singleton(IpdService::class);
        $this->app->singleton(BillingService::class);
        $this->app->singleton(CashRegisterService::class);
        $this->app->singleton(ExpenseService::class);
        $this->app->singleton(TeleconsultationService::class);

        $this->app->singleton(CashfreePaymentProvider::class);
        $this->app->singleton(CashfreeSubscriptionProvider::class);
        $this->app->singleton(WebhookProcessor::class);

        $this->app->bind(PaymentGatewayInterface::class, CashfreePaymentProvider::class);
        $this->app->bind(SubscriptionProviderInterface::class, CashfreeSubscriptionProvider::class);

        $this->app->singleton(GoogleMeetProvider::class);
        $this->app->bind(VideoProviderInterface::class, GoogleMeetProvider::class);

        $this->app->singleton(MetaWhatsAppProvider::class);
        $this->app->singleton(Msg91SmsProvider::class);
        $this->app->singleton(ResendEmailProvider::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(NotificationTemplateService::class);
        $this->app->singleton(\App\Services\Staff\DoctorService::class);
        $this->app->singleton(\App\Services\Treatments\TreatmentCatalogService::class);
        $this->app->singleton(\App\Services\IPD\IpdConfigurationService::class);
        $this->app->singleton(\App\Services\Billing\ReceiptService::class);
        $this->app->singleton(\App\Services\Plans\PlanService::class);

        $this->app->bind(WhatsAppProviderInterface::class, MetaWhatsAppProvider::class);
        $this->app->bind(SmsProviderInterface::class, Msg91SmsProvider::class);
        $this->app->bind(EmailProviderInterface::class, ResendEmailProvider::class);

        $this->app->singleton(GeminiProvider::class);
        $this->app->singleton(AIContextBuilder::class);
        $this->app->singleton(AIManager::class);
        $this->app->bind(AIProviderInterface::class, GeminiProvider::class);

        $this->app->singleton(S3StorageProvider::class);
        $this->app->singleton(DocumentService::class);
        $this->app->singleton(PdfService::class);
        $this->app->singleton(\App\Services\Documents\OcrService::class);
        $this->app->singleton(\App\Services\Documents\SpeechService::class);
        $this->app->singleton(\App\Services\Bookings\OnlineBookingService::class);
        $this->app->bind(StorageProviderInterface::class, S3StorageProvider::class);

        // OCR provider — config-driven (OCR_PROVIDER env: google_vision|tesseract).
        // Both implementations degrade gracefully when unconfigured, so the app
        // boots without any OCR credentials. The resolved provider may still report
        // isConfigured() === false; callers must check before relying on results.
        $this->app->singleton(OCRProviderInterface::class, function ($app) {
            return match ((string) config('services.ocr_provider', 'google_vision')) {
                'tesseract' => $app->make(TesseractOcrProvider::class),
                default => $app->make(GoogleVisionOcrProvider::class),
            };
        });

        // Speech-to-text provider — config-driven (SPEECH_PROVIDER env: openai|whisper_cpp).
        $this->app->singleton(SpeechProviderInterface::class, function ($app) {
            return match ((string) config('services.speech_provider', 'openai')) {
                'whisper_cpp' => $app->make(WhisperCppProvider::class),
                default => $app->make(OpenAiWhisperProvider::class),
            };
        });

        $this->app->singleton(ReportService::class);

        $this->app->singleton(TokenService::class);

        $this->app->singleton(TwoFactorService::class, function ($app) {
            return new TwoFactorService(
                new Google2FA,
                $app->make('encrypter'),
            );
        });
    }

    public function boot(): void
    {
        // DB-backed integration accounts (active, global scope) override the
        // env-based services.* config — providers read config at construction.
        // Guarded so a fresh install (no table yet) still boots.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('integration_accounts')) {
                app(\App\Services\Integrations\IntegrationAccountService::class)->applyToConfig();
            }
        } catch (\Throwable) {
            // DB unreachable at boot (e.g. migrations in flight) — env config stays.
        }

        // The App\Livewire\AI namespace kebab-cases to a-i.* — register
        // friendlier aliases for blade mounting.
        \Livewire\Livewire::component('ai.summary-panel', \App\Livewire\AI\SummaryPanel::class);
        // Before any policy check, resolve granular RBAC permissions through
        // PermissionService. A permission key is a dotted "<module>.<action>"
        // string; anything else falls through to policy resolution.
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, '.')) {
                return app(PermissionService::class)->can($user, $ability);
            }

            return null;
        });

        // TreatmentBooking's policy is named TreatmentPolicy (not the
        // auto-discovered TreatmentBookingPolicy), so register it explicitly.
        Gate::policy(TreatmentBooking::class, TreatmentPolicy::class);
        Gate::policy(TreatmentService::class, TreatmentCatalogPolicy::class);
        Gate::policy(TreatmentRoom::class, TreatmentCatalogPolicy::class);
        Gate::policy(IpdWard::class, IpdConfigurationPolicy::class);
        Gate::policy(IpdRoom::class, IpdConfigurationPolicy::class);
        Gate::policy(IpdBed::class, IpdConfigurationPolicy::class);

        // Plans/Subscriptions share SubscriptionPolicy with non-standard method names.
        Gate::policy(Plan::class, SubscriptionPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        // IpdAdmission's policy is named IpdPolicy (not IpdAdmissionPolicy).
        Gate::policy(IpdAdmission::class, IpdPolicy::class);

        // Async notification wiring: domain events dispatch queued jobs so
        // WhatsApp/SMS/email/AI provider calls never block the request path.
        // See app/Events, app/Listeners, app/Jobs.
        Event::listen(
            \App\Events\AppointmentBooked::class,
            \App\Listeners\SendAppointmentBookedNotification::class
        );
        Event::listen(
            \App\Events\AppointmentConfirmed::class,
            \App\Listeners\SendAppointmentConfirmedNotification::class
        );
        Event::listen(
            \App\Events\PaymentRecorded::class,
            \App\Listeners\SendPaymentReceiptNotification::class
        );

        // Async document pipeline: OCR extraction runs as a queued job.
        Event::listen(
            \App\Events\DocumentUploaded::class,
            \App\Listeners\DispatchDocumentOcr::class
        );
    }
}
