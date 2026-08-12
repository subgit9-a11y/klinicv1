<?php

use App\Http\Controllers\DashboardController;
use App\Livewire\Profile\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'active', 'verified', '2fa'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', Profile::class)->name('profile');
});

require __DIR__.'/auth.php';
