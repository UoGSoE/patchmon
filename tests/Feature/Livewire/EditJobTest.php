<?php

use App\Enums\GraceUnit;
use App\Livewire\EditJob;
use App\Models\Job;
use App\Models\User;
use Livewire\Livewire;

it('forbids a stranger from editing someone elses job', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $job = Job::factory()->forUser($owner)->create();

    $this->actingAs($stranger)
        ->get(route('jobs.edit', $job))
        ->assertForbidden();
});

it('pre-fills the form with the current job and saves changes back', function () {
    $owner = User::factory()->create();
    $job = Job::factory()->forUser($owner)->withCron('0 2 * * *')->create([
        'name' => 'Old name',
        'grace_value' => 5,
        'grace_units' => GraceUnit::Minutes,
    ]);

    Livewire::actingAs($owner)
        ->test(EditJob::class, ['job' => $job])
        ->assertSet('form.name', 'Old name')
        ->assertSet('form.cron_expression', '0 2 * * *')
        ->set('form.name', 'Renamed nightly backup')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('jobs.show', $job));

    expect($job->fresh()->name)->toBe('Renamed nightly backup');
});
