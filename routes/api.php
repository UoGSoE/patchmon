<?php

use App\Http\Controllers\Api\V1\Admin\ApiTokensController as AdminApiTokensController;
use App\Http\Controllers\Api\V1\Admin\TeamMembersController as AdminTeamMembersController;
use App\Http\Controllers\Api\V1\Admin\TeamsController as AdminTeamsController;
use App\Http\Controllers\Api\V1\Admin\UsersController as AdminUsersController;
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

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::middleware('ability:admin:read')->group(function () {
            Route::get('/teams', [AdminTeamsController::class, 'index'])->name('api.v1.admin.teams.index');
            Route::get('/teams/{team}', [AdminTeamsController::class, 'show'])->name('api.v1.admin.teams.show');
            Route::get('/users', [AdminUsersController::class, 'index'])->name('api.v1.admin.users.index');
            Route::get('/users/{user}', [AdminUsersController::class, 'show'])->name('api.v1.admin.users.show');
            Route::get('/api-tokens', [AdminApiTokensController::class, 'index'])->name('api.v1.admin.api-tokens.index');
        });
        Route::middleware('ability:admin:write')->group(function () {
            Route::post('/teams', [AdminTeamsController::class, 'store'])->name('api.v1.admin.teams.store');
            Route::patch('/teams/{team}', [AdminTeamsController::class, 'update'])->name('api.v1.admin.teams.update');
            Route::delete('/teams/{team}', [AdminTeamsController::class, 'destroy'])->name('api.v1.admin.teams.destroy');
            Route::post('/teams/{team}/members', [AdminTeamMembersController::class, 'store'])->name('api.v1.admin.teams.members.store');
            Route::delete('/teams/{team}/members/{user}', [AdminTeamMembersController::class, 'destroy'])->name('api.v1.admin.teams.members.destroy');
            Route::post('/users', [AdminUsersController::class, 'store'])->name('api.v1.admin.users.store');
            Route::patch('/users/{user}', [AdminUsersController::class, 'update'])->name('api.v1.admin.users.update');
            Route::delete('/users/{user}', [AdminUsersController::class, 'destroy'])->name('api.v1.admin.users.destroy');
        });
    });
});
