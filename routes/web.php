<?php

use App\Contracts\StorageProviderInterface;
use App\Http\Controllers\AiController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\IpdController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnlineBookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TreatmentController;
use App\Livewire\Appointments\AppointmentBoard;
use App\Livewire\EMR\ConsultationBoard;
use App\Livewire\Patients\Patient360;
use App\Livewire\Patients\PatientEdit;
use App\Livewire\Patients\PatientList;
use App\Livewire\Profile\Profile;
use App\Livewire\Queue\QueueBoard;
use App\Livewire\SuperAdmin\ConfigurationPanel;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Guest-accessible online consultation booking.
Route::get('/book', [OnlineBookingController::class, 'show'])->name('online-booking.show');
Route::get('/book/slots', [OnlineBookingController::class, 'slots'])->name('online-booking.slots');
Route::post('/book', [OnlineBookingController::class, 'store'])->name('online-booking.store');

Route::middleware(['auth', 'active', 'verified', '2fa', 'tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', Profile::class)->name('profile');

    Route::get('/patients', PatientList::class)->name('patients.index');
    Route::get('/patients/{patient}/edit', PatientEdit::class)->name('patients.edit');
    Route::get('/patients/{patient}', Patient360::class)->name('patients.show');

    Route::get('/appointments', AppointmentBoard::class)->name('appointments.index');
    Route::get('/queue', QueueBoard::class)->name('queue.index');
    Route::get('/emr', ConsultationBoard::class)->name('emr.index');
    Route::get('/emr/{patientId}', ConsultationBoard::class)->name('emr.patient');

    Route::get('/treatments', [TreatmentController::class, 'index'])->name('treatments.index');
    Route::post('/treatments', [TreatmentController::class, 'book'])->name('treatments.book');
    Route::post('/treatments/{treatment}/complete', [TreatmentController::class, 'complete'])->name('treatments.complete');
    Route::post('/treatments/{treatment}/cancel', [TreatmentController::class, 'cancel'])->name('treatments.cancel');

    Route::get('/prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show'])->name('prescriptions.show');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/add', [SettingController::class, 'store'])->name('settings.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/stream', [DocumentController::class, 'streamDocument'])->name('documents.show');
    Route::get('/prescriptions/{prescription}/pdf', [DocumentController::class, 'downloadPrescription'])->name('prescriptions.pdf');
    Route::get('/invoices/{invoice}/pdf', [DocumentController::class, 'downloadInvoice'])->name('invoices.pdf');

    Route::get('/ai', [AiController::class, 'index'])->name('ai.index');
    Route::post('/ai/generate', [AiController::class, 'generate'])->name('ai.generate');
    Route::post('/ai/{aiRequest}/approve', [AiController::class, 'approve'])->name('ai.approve');
    Route::post('/ai/{aiRequest}/reject', [AiController::class, 'reject'])->name('ai.reject');

    // Livewire AI governance board (draft → approve/reject).
    Route::get('/ai/board', App\Livewire\AI\AiApprovalBoard::class)->name('ai.board');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{delivery}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/ipd', [IpdController::class, 'index'])->name('ipd.index');
    Route::post('/ipd/admit', [IpdController::class, 'admit'])->name('ipd.admit');
    Route::post('/ipd/{admission}/transfer', [IpdController::class, 'transferBed'])->name('ipd.transfer');
    Route::post('/ipd/{admission}/discharge', [IpdController::class, 'discharge'])->name('ipd.discharge');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/invoices', [BillingController::class, 'createInvoice'])->name('billing.invoices.create');
    Route::post('/billing/invoices/{invoice}/items', [BillingController::class, 'addItem'])->name('billing.items.add');
    Route::post('/billing/invoices/{invoice}/issue', [BillingController::class, 'issue'])->name('billing.issue');
    Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'recordPayment'])->name('billing.pay');
    Route::post('/billing/invoices/{invoice}/void', [BillingController::class, 'void'])->name('billing.void');

    Route::get('/documents/stream/{path}', function (string $path) {
        $decoded = base64_decode($path, true);
        if ($decoded === false) {
            abort(400);
        }
        if (! request()->hasValidSignature()) {
            abort(403);
        }
        $provider = app(StorageProviderInterface::class);
        if (! $provider->exists($decoded)) {
            abort(404);
        }

        return response()->stream(function () use ($provider, $decoded) {
            $stream = $provider->stream($decoded);
            fpassthru($stream);
            fclose($stream);
        }, 200, ['Content-Type' => 'application/octet-stream']);
    })->where('path', '[^/]+')->name('documents.stream')->withoutMiddleware(['auth', '2fa']);
});

// Public self-service clinic onboarding (guest only, throttled).
Route::get('/signup', \App\Livewire\Onboarding\ClinicSignup::class)
    ->name('onboarding.signup')
    ->middleware(['guest', 'throttle:6,1']);

Route::middleware(['auth', 'active', 'verified', '2fa'])->group(function () {
    Route::get('/onboarding/setup', \App\Livewire\Onboarding\ClinicSetupWizard::class)
        ->name('onboarding.setup');

    Route::get('/super-admin/configuration', ConfigurationPanel::class)
        ->name('super-admin.configuration');
    Route::get('/super-admin/tenants', \App\Livewire\SuperAdmin\TenantManagement::class)
        ->name('super-admin.tenants');
    Route::get('/super-admin/users', \App\Livewire\SuperAdmin\UserManagement::class)
        ->name('super-admin.users');
    Route::get('/super-admin/subscriptions', \App\Livewire\SuperAdmin\SubscriptionManagement::class)
        ->name('super-admin.subscriptions');
    Route::get('/super-admin/integrations', \App\Livewire\SuperAdmin\IntegrationStatus::class)
        ->name('super-admin.integrations');
    Route::get('/super-admin/operations', \App\Livewire\SuperAdmin\OperationsCenter::class)
        ->name('super-admin.operations');
});

require __DIR__.'/auth.php';
