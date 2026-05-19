<?php

use Illuminate\Support\Facades\Route;


require __DIR__ . '/sso-auth.php';
Route::middleware('auth')->group(function () {
    Route::get('/', \App\Livewire\HomePage::class)->name('home');
});

