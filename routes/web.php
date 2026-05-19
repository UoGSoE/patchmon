<?php

use App\Http\Controllers\CheckInController;
use App\Livewire\CreateJob;
use App\Livewire\EditJob;
use App\Livewire\HomePage;
use App\Livewire\JobDetail;
use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';

Route::get('/check-in/{token}', CheckInController::class)
    ->name('check-in');

Route::middleware('auth')->group(function () {
    Route::get('/', HomePage::class)->name('home');
    Route::get('/jobs/create', CreateJob::class)->name('jobs.create');
    Route::get('/jobs/{job}', JobDetail::class)->name('jobs.show');
    Route::get('/jobs/{job}/edit', EditJob::class)->name('jobs.edit');
});
