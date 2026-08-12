<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Minimal auth surface for Phase 1 foundation. Full authentication
// (registration, email verification, password reset, 2FA) is built in Phase 3.
Route::get('login', function () {
    return view('auth.login');
})->name('login');

Route::post('login', function (Request $request) {
    return back()->withErrors(['email' => 'Authentication is configured in Phase 3.']);
})->name('login.post');

Route::post('logout', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');
