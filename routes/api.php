<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\ConsultationController;
use App\Http\Controllers\Api\V1\FollowupController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\IpdAdmissionController;
use App\Http\Controllers\Api\V1\InvestigationController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\TeleconsultationController;
use App\Http\Controllers\Api\V1\TreatmentBookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public: token issuance (strict rate limit to deter brute force).
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Payment webhooks (signed separately, not token-authed).
    Route::post('/webhooks/payments', function () {
        return response()->json(['message' => 'Webhook received.']);
    })->name('api.webhooks.payments');

    // Authenticated endpoints (rate limited per token).
    Route::middleware(['auth.api', 'throttle:60,1'])->name('api.')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('patients', PatientController::class);
        Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show']);
        Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
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
        Route::apiResource('teleconsultations', TeleconsultationController::class)->only(['index', 'show']);
    });
});
