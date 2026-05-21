<?php

use App\Http\Controllers\PatchEventController;
use App\Livewire\Admin\ApiTokens as AdminApiTokens;
use App\Livewire\Admin\TeamDetail as AdminTeamDetail;
use App\Livewire\Admin\Teams as AdminTeams;
use App\Livewire\Admin\Users as AdminUsers;
use App\Livewire\AdminDashboard;
use App\Livewire\ApiHelp;
use App\Livewire\HomePage;
use App\Livewire\MySettings;
use App\Livewire\ServerDetail;
use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';

Route::match(['get', 'post'], '/record-patch/{token}', PatchEventController::class)
    ->name('record-patch');

Route::middleware('auth')->group(function () {
    Route::get('/', HomePage::class)->name('home');
    Route::get('/servers/{server}', ServerDetail::class)->name('servers.show');

    Route::get('/settings', MySettings::class)->name('settings');
    Route::get('/api/help', ApiHelp::class)->name('api.help');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboard::class)->name('dashboard');
        Route::get('/teams', AdminTeams::class)->name('teams.index');
        Route::get('/teams/{team}', AdminTeamDetail::class)->name('teams.show');
        Route::get('/users', AdminUsers::class)->name('users.index');
        Route::get('/api-tokens', AdminApiTokens::class)->name('api-tokens.index');
    });
});
