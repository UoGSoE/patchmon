<?php

use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Auth\SSOController::class, 'login'])->name('login');
    // Or as a Livewire component if you prefer
    // Route::get('/login', App\Livewire\Login::class)->name('login');
});

// SSO specific routes
Route::post('/login', [\App\Http\Controllers\Auth\SSOController::class, 'localLogin'])->name('login.local');
Route::get('/login/sso', [\App\Http\Controllers\Auth\SSOController::class, 'ssoLogin'])->name('login.sso');
Route::get('/auth/callback', [\App\Http\Controllers\Auth\SSOController::class, 'handleProviderCallback'])->name('sso.callback');
Route::post('/logout', [\App\Http\Controllers\Auth\SSOController::class, 'logout'])->name('auth.logout');
Route::get('/logged-out', [\App\Http\Controllers\Auth\SSOController::class, 'loggedOut'])->name('logged_out');
