<?php

use App\Http\Controllers\Api\V1\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Api\V1\Admin\ApiTokensController as AdminApiTokensController;
use App\Http\Controllers\Api\V1\Admin\TeamMembersController as AdminTeamMembersController;
use App\Http\Controllers\Api\V1\Admin\TeamsController as AdminTeamsController;
use App\Http\Controllers\Api\V1\Admin\UsersController as AdminUsersController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ServersController;
use App\Http\Controllers\Api\V1\TeamsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/me', [MeController::class, 'show'])->name('api.v1.me');
    Route::get('/teams', [TeamsController::class, 'index'])->name('api.v1.teams.index');

    Route::middleware('ability:servers:read')->group(function () {
        Route::get('/servers', [ServersController::class, 'index'])->name('api.v1.servers.index');
        Route::get('/servers/{server}', [ServersController::class, 'show'])->name('api.v1.servers.show');
        Route::get('/servers/{server}/patch-events', [ServersController::class, 'patchEvents'])->name('api.v1.servers.patch-events');
    });

    Route::middleware('ability:servers:write')->group(function () {
        Route::post('/servers', [ServersController::class, 'store'])->name('api.v1.servers.store');
        Route::patch('/servers/{server}', [ServersController::class, 'update'])->name('api.v1.servers.update');
        Route::delete('/servers/{server}', [ServersController::class, 'destroy'])->name('api.v1.servers.destroy');
        Route::post('/servers/{server}/silence', [ServersController::class, 'silence'])->name('api.v1.servers.silence');
        Route::delete('/servers/{server}/silence', [ServersController::class, 'unsilence'])->name('api.v1.servers.unsilence');
    });

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::middleware('ability:admin:read')->group(function () {
            Route::get('/teams', [AdminTeamsController::class, 'index'])->name('api.v1.admin.teams.index');
            Route::get('/teams/{team}', [AdminTeamsController::class, 'show'])->name('api.v1.admin.teams.show');
            Route::get('/users', [AdminUsersController::class, 'index'])->name('api.v1.admin.users.index');
            Route::get('/users/{user}', [AdminUsersController::class, 'show'])->name('api.v1.admin.users.show');
            Route::get('/api-tokens', [AdminApiTokensController::class, 'index'])->name('api.v1.admin.api-tokens.index');
            Route::get('/activity', [AdminActivityLogController::class, 'index'])->name('api.v1.admin.activity.index');
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
