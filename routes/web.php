<?php

use App\Http\Controllers\DashboardController;
use App\Livewire\Appointments\AppointmentBoard;
use App\Livewire\Patients\Patient360;
use App\Livewire\Patients\PatientEdit;
use App\Livewire\Patients\PatientList;
use App\Livewire\Profile\Profile;
use App\Livewire\Queue\QueueBoard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'active', 'verified', '2fa', 'tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', Profile::class)->name('profile');

    Route::get('/patients', PatientList::class)->name('patients.index');
    Route::get('/patients/{patient}/edit', PatientEdit::class)->name('patients.edit');
    Route::get('/patients/{patient}', Patient360::class)->name('patients.show');

    Route::get('/appointments', AppointmentBoard::class)->name('appointments.index');
    Route::get('/queue', QueueBoard::class)->name('queue.index');
    Route::get('/emr', \App\Livewire\EMR\ConsultationBoard::class)->name('emr.index');
    Route::get('/emr/{patientId}', \App\Livewire\EMR\ConsultationBoard::class)->name('emr.patient');

    Route::get('/documents/stream/{path}', function (string $path) {
        $decoded = base64_decode($path, true);
        if ($decoded === false) {
            abort(400);
        }
        if (!request()->hasValidSignature()) {
            abort(403);
        }
        $provider = app(\App\Contracts\StorageProviderInterface::class);
        if (!$provider->exists($decoded)) {
            abort(404);
        }
        return response()->stream(function () use ($provider, $decoded) {
            $stream = $provider->stream($decoded);
            fpassthru($stream);
            fclose($stream);
        }, 200, ['Content-Type' => 'application/octet-stream']);
    })->where('path', '[^/]+')->name('documents.stream')->withoutMiddleware(['auth', '2fa']);
});

Route::middleware(['auth', 'active', 'verified', '2fa'])->group(function () {
    Route::get('/super-admin/configuration', \App\Livewire\SuperAdmin\ConfigurationPanel::class)
        ->name('super-admin.configuration');
});

require __DIR__.'/auth.php';
