<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AiRequestController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\ConsultationController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FollowupController;
use App\Http\Controllers\Api\V1\InvestigationController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\IpdAdmissionController;
use App\Http\Controllers\Api\V1\IpdConfigurationController;
use App\Http\Controllers\Api\V1\NotificationTemplateController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\TeleconsultationController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TreatmentBookingController;
use App\Http\Controllers\Api\V1\TreatmentCatalogController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public: token issuance (strict rate limit to deter brute force).
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Payment webhooks (signed separately, not token-authed).
    Route::post('/webhooks/payments', [WebhookController::class, 'payments'])
        ->name('api.webhooks.payments');

    // Authenticated endpoints (rate limited per token).
    Route::middleware(['auth.api', 'throttle:60,1'])->name('api.')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('patients', PatientController::class);
        Route::get('/appointments/slots', [AppointmentController::class, 'availableSlots']);
        Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show']);
        Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::put('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
        Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'changeStatus']);
        Route::apiResource('consultations', ConsultationController::class)->only(['index', 'store', 'show']);
        Route::put('/consultations/{consultation}', [ConsultationController::class, 'update']);
        Route::post('/consultations/{consultation}/complete', [ConsultationController::class, 'complete']);
        Route::post('/consultations/{consultation}/amend', [ConsultationController::class, 'amend']);
        Route::post('/consultations/{consultation}/vitals', [ConsultationController::class, 'recordVitals']);
        Route::post('/consultations/{consultation}/diagnoses', [ConsultationController::class, 'addDiagnosis']);
        Route::post('/consultations/{consultation}/notes', [ConsultationController::class, 'addNote']);
        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
        Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
        Route::post('/invoices/{invoice}/payments/{payment}/refund', [InvoiceController::class, 'refund']);
        Route::post('/invoices/{invoice}/payments/{payment}/receipt', [ReceiptController::class, 'generate']);
        Route::get('/invoices/{invoice}/payments/{payment}/receipt', [ReceiptController::class, 'download']);
        Route::apiResource('treatments', TreatmentBookingController::class)->only(['index', 'store', 'show']);
        Route::post('/treatments/{treatmentBooking}/complete', [TreatmentBookingController::class, 'complete']);
        Route::post('/treatments/{treatmentBooking}/cancel', [TreatmentBookingController::class, 'cancel']);
        Route::apiResource('ipd-admissions', IpdAdmissionController::class)->only(['index', 'store', 'show']);
        Route::post('/ipd-admissions/{ipdAdmission}/discharge', [IpdAdmissionController::class, 'discharge']);
        Route::apiResource('patients.prescriptions', PrescriptionController::class)->only(['index', 'store', 'show'])->shallow();
        Route::apiResource('patients.followups', FollowupController::class)->only(['index', 'store']);
        Route::post('/followups/{followup}/status', [FollowupController::class, 'updateStatus']);
        Route::apiResource('patients.investigations', InvestigationController::class)->only(['index', 'store']);
        Route::patch('/investigations/{investigation}', [InvestigationController::class, 'update']);
        Route::apiResource('patients.consents', ConsentController::class)->only(['index', 'store']);
        Route::post('/consents/{consent}/revoke', [ConsentController::class, 'revoke']);
        Route::apiResource('teleconsultations', TeleconsultationController::class)->only(['index', 'store', 'show']);
        Route::post('/teleconsultations/{teleconsultation}/start', [TeleconsultationController::class, 'start']);
        Route::post('/teleconsultations/{teleconsultation}/end', [TeleconsultationController::class, 'end']);
        Route::post('/teleconsultations/{teleconsultation}/cancel', [TeleconsultationController::class, 'cancel']);
        Route::get('/cash-registers', [CashRegisterController::class, 'index']);
        Route::post('/cash-registers', [CashRegisterController::class, 'open']);
        Route::get('/cash-registers/{cashRegister}', [CashRegisterController::class, 'show']);
        Route::post('/cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close']);
        Route::get('/cash-registers/{cashRegister}/entries', [CashRegisterController::class, 'entries']);
        Route::apiResource('expenses', ExpenseController::class)->only(['index', 'store', 'show']);
        Route::apiResource('notification-templates', NotificationTemplateController::class);
        Route::apiResource('doctors', DoctorController::class)->only(['index', 'store', 'show', 'update']);
        Route::get('/doctors/{doctor}/availability', [DoctorController::class, 'availability']);
        Route::put('/doctors/{doctor}/availability', [DoctorController::class, 'setAvailability']);

        // Treatment catalogue (services + rooms)
        Route::get('/treatment-services', [TreatmentCatalogController::class, 'indexServices']);
        Route::post('/treatment-services', [TreatmentCatalogController::class, 'storeService']);
        Route::get('/treatment-services/{treatment_service}', [TreatmentCatalogController::class, 'showService']);
        Route::put('/treatment-services/{treatment_service}', [TreatmentCatalogController::class, 'updateService']);
        Route::delete('/treatment-services/{treatment_service}', [TreatmentCatalogController::class, 'destroyService']);
        Route::get('/treatment-rooms', [TreatmentCatalogController::class, 'indexRooms']);
        Route::post('/treatment-rooms', [TreatmentCatalogController::class, 'storeRoom']);
        Route::delete('/treatment-rooms/{treatment_room}', [TreatmentCatalogController::class, 'destroyRoom']);

        // IPD configuration (wards → rooms → beds)
        Route::get('/ipd-wards', [IpdConfigurationController::class, 'indexWards']);
        Route::post('/ipd-wards', [IpdConfigurationController::class, 'storeWard']);
        Route::get('/ipd-wards/{ipd_ward}', [IpdConfigurationController::class, 'showWard']);
        Route::put('/ipd-wards/{ipd_ward}', [IpdConfigurationController::class, 'updateWard']);
        Route::delete('/ipd-wards/{ipd_ward}', [IpdConfigurationController::class, 'destroyWard']);
        Route::get('/ipd-rooms', [IpdConfigurationController::class, 'indexRooms']);
        Route::post('/ipd-rooms', [IpdConfigurationController::class, 'storeRoom']);
        Route::delete('/ipd-rooms/{ipd_room}', [IpdConfigurationController::class, 'destroyRoom']);
        Route::get('/ipd-beds', [IpdConfigurationController::class, 'indexBeds']);
        Route::post('/ipd-beds', [IpdConfigurationController::class, 'storeBed']);
        Route::patch('/ipd-beds/{ipd_bed}/status', [IpdConfigurationController::class, 'updateBedStatus']);
        Route::delete('/ipd-beds/{ipd_bed}', [IpdConfigurationController::class, 'destroyBed']);

        // Plans & subscriptions (SaaS lifecycle)
        Route::get('/plans', [SubscriptionController::class, 'indexPlans']);
        Route::get('/plans/{plan}', [SubscriptionController::class, 'showPlan']);
        Route::get('/subscription', [SubscriptionController::class, 'currentSubscription']);
        Route::post('/subscription/activate', [SubscriptionController::class, 'activate']);
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);

        // AI governance (draft → approve/reject)
        Route::get('/ai-requests', [AiRequestController::class, 'index']);
        Route::post('/ai-requests', [AiRequestController::class, 'generate']);
        Route::get('/ai-requests/{aiRequest}', [AiRequestController::class, 'show']);
        Route::post('/ai-requests/{aiRequest}/approve', [AiRequestController::class, 'approve']);
        Route::post('/ai-requests/{aiRequest}/reject', [AiRequestController::class, 'reject']);
    });
});
