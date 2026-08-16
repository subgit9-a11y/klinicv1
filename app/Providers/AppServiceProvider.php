<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AI\AIContextBuilder;
use App\Services\AI\AIManager;
use App\Services\AI\GeminiProvider;
use App\Services\Appointments\AppointmentService;
use App\Services\Auth\PermissionService;
use App\Services\Auth\TokenService;
use App\Services\Auth\TwoFactorService;
use App\Services\Billing\BillingService;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\EMR\ConsultationService;
use App\Services\EMR\PrescriptionService;
use App\Services\EMR\VitalsService;
use App\Services\IPD\IpdService;
use App\Services\Notifications\NotificationService;
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
use App\Services\Tenancy\TenantContext;
use App\Services\Video\GoogleMeetProvider;
use App\Services\Treatments\TreatmentBookingService;
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

        $this->app->singleton(CashfreePaymentProvider::class);
        $this->app->singleton(CashfreeSubscriptionProvider::class);
        $this->app->singleton(WebhookProcessor::class);

        $this->app->bind(\App\Contracts\PaymentGatewayInterface::class, CashfreePaymentProvider::class);
        $this->app->bind(\App\Contracts\SubscriptionProviderInterface::class, CashfreeSubscriptionProvider::class);

        $this->app->singleton(GoogleMeetProvider::class);
        $this->app->bind(\App\Contracts\VideoProviderInterface::class, GoogleMeetProvider::class);

        $this->app->singleton(MetaWhatsAppProvider::class);
        $this->app->singleton(Msg91SmsProvider::class);
        $this->app->singleton(ResendEmailProvider::class);
        $this->app->singleton(NotificationService::class);

        $this->app->bind(\App\Contracts\WhatsAppProviderInterface::class, MetaWhatsAppProvider::class);
        $this->app->bind(\App\Contracts\SmsProviderInterface::class, Msg91SmsProvider::class);
        $this->app->bind(\App\Contracts\EmailProviderInterface::class, ResendEmailProvider::class);

        $this->app->singleton(GeminiProvider::class);
        $this->app->singleton(AIContextBuilder::class);
        $this->app->singleton(AIManager::class);
        $this->app->bind(\App\Contracts\AIProviderInterface::class, GeminiProvider::class);

        $this->app->singleton(S3StorageProvider::class);
        $this->app->singleton(DocumentService::class);
        $this->app->singleton(PdfService::class);
        $this->app->bind(\App\Contracts\StorageProviderInterface::class, S3StorageProvider::class);

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
        Gate::policy(\App\Models\TreatmentBooking::class, \App\Policies\TreatmentPolicy::class);

        // IpdAdmission's policy is named IpdPolicy (not IpdAdmissionPolicy).
        Gate::policy(\App\Models\IpdAdmission::class, \App\Policies\IpdPolicy::class);
    }
}
