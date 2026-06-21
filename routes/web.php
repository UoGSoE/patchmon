<?php

use App\Http\Controllers\DownloadRecordPatchScript;
use App\Http\Controllers\PatchEventController;
use App\Http\Controllers\ProvisionPatchTokenController;
use App\Livewire\Admin\ApiTokens as AdminApiTokens;
use App\Livewire\Admin\TeamDetail as AdminTeamDetail;
use App\Livewire\Admin\Teams as AdminTeams;
use App\Livewire\Admin\Users as AdminUsers;
use App\Livewire\AdminDashboard;
use App\Livewire\ApiHelp;
use App\Livewire\HomePage;
use App\Livewire\ImportServers;
use App\Livewire\MySettings;
use App\Livewire\ServerDetail;
use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';

// Declared before the {token} route below — otherwise "provision" is matched as a
// {token} value and 404s.
Route::post('/record-patch/provision', ProvisionPatchTokenController::class)
    ->name('record-patch.provision');

Route::post('/record-patch/{token}', PatchEventController::class)
    ->name('record-patch');

Route::middleware('auth')->group(function () {
    Route::get('/', HomePage::class)->name('home');
    Route::get('/servers/{server}', ServerDetail::class)->name('servers.show');
    Route::get('/import', ImportServers::class)->name('import');

    Route::get('/settings', MySettings::class)->name('settings');
    Route::get('/api/help', ApiHelp::class)->name('api.help');
    Route::get('/scripts/record_patched.sh', DownloadRecordPatchScript::class)
        ->defaults('filename', 'record_patched.sh')
        ->name('scripts.record-patch');
    Route::get('/scripts/record_patched.ps1', DownloadRecordPatchScript::class)
        ->defaults('filename', 'record_patched.ps1')
        ->name('scripts.record-patch-ps');

    // The dashboard is the estate overview — admins and oversight admins (the
    // chase-up folk) can see it. Everything else under /admin stays admin-only.
    Route::get('/admin', AdminDashboard::class)
        ->middleware('can:viewDashboard')
        ->name('admin.dashboard');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/teams', AdminTeams::class)->name('teams.index');
        Route::get('/teams/{team}', AdminTeamDetail::class)->name('teams.show');
        Route::get('/users', AdminUsers::class)->name('users.index');
        Route::get('/api-tokens', AdminApiTokens::class)->name('api-tokens.index');
    });
});
