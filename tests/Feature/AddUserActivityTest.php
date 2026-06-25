<?php

use App\Models\ActivityLog;

it('logs creating a user via the console command as an automated action', function () {
    $this->artisan('patchmon:add-user', [
        'username' => 'cli2x',
        'email' => 'cli@example.test',
        'surname' => 'Lineman',
        'forenames' => 'Comm',
    ])->assertSuccessful();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBeNull();
    expect($log->actorLabel())->toBe('Automated');
    expect($log->description)->toContain('Comm Lineman');
});
