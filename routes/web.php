<?php

use App\Http\Controllers\DashboardController;
use App\Livewire\Patients\Patient360;
use App\Livewire\Patients\PatientEdit;
use App\Livewire\Patients\PatientList;
use App\Livewire\Profile\Profile;
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
});

require __DIR__.'/auth.php';
