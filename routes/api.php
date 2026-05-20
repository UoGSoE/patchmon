<?php

use App\Http\Controllers\Api\V1\JobsController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\TeamsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/me', [MeController::class, 'show'])->name('api.v1.me');
    Route::get('/teams', [TeamsController::class, 'index'])->name('api.v1.teams.index');

    Route::middleware('ability:jobs:read')->group(function () {
        Route::get('/jobs', [JobsController::class, 'index'])->name('api.v1.jobs.index');
        Route::get('/jobs/{job}', [JobsController::class, 'show'])->name('api.v1.jobs.show');
        Route::get('/jobs/{job}/check-ins', [JobsController::class, 'checkIns'])->name('api.v1.jobs.check-ins');
    });

    Route::middleware('ability:jobs:write')->group(function () {
        Route::post('/jobs', [JobsController::class, 'store'])->name('api.v1.jobs.store');
        Route::patch('/jobs/{job}', [JobsController::class, 'update'])->name('api.v1.jobs.update');
        Route::delete('/jobs/{job}', [JobsController::class, 'destroy'])->name('api.v1.jobs.destroy');
        Route::post('/jobs/{job}/silence', [JobsController::class, 'silence'])->name('api.v1.jobs.silence');
        Route::delete('/jobs/{job}/silence', [JobsController::class, 'unsilence'])->name('api.v1.jobs.unsilence');
    });
});
