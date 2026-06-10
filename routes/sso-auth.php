<?php

use App\Http\Controllers\Auth\SSOController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SSOController::class, 'login'])->name('login');
    // Or as a Livewire component if you prefer
    // Route::get('/login', App\Livewire\Login::class)->name('login');
});

// SSO specific routes
Route::post('/login', [SSOController::class, 'localLogin'])->name('login.local');
Route::get('/login/sso', [SSOController::class, 'ssoLogin'])->name('login.sso');
Route::get('/auth/callback', [SSOController::class, 'handleProviderCallback'])->name('sso.callback');
Route::post('/logout', [SSOController::class, 'logout'])->name('auth.logout');
Route::get('/logged-out', [SSOController::class, 'loggedOut'])->name('logged_out');
