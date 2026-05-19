<?php

use App\Livewire\JobDetail;
use App\Models\Job;
use App\Models\User;
use Livewire\Livewire;

it('unsilences the job when the owner flips the toggle off', function () {
    $owner = User::factory()->create();
    $job = Job::factory()->forUser($owner)->silenced()->create();

    Livewire::actingAs($owner)
        ->test(JobDetail::class, ['job' => $job])
        ->set('silenced', false);

    $job->refresh();
    expect($job->silenced_until)->toBeNull()
        ->and($job->silence_reason)->toBeNull();
});

it('silences the job when the owner flips the toggle on', function () {
    $owner = User::factory()->create();
    $job = Job::factory()->forUser($owner)->create();
    $until = now()->addDay()->startOfSecond();

    Livewire::actingAs($owner)
        ->test(JobDetail::class, ['job' => $job])
        ->set('silenceUntil', $until->toDateTimeLocalString())
        ->set('silenceReason', 'Power works')
        ->set('silenced', true);

    $job->refresh();
    expect($job->silenced_until)->not->toBeNull()
        ->and($job->silence_reason)->toBe('Power works');
});

it('saves changes to until and reason while already silenced', function () {
    $owner = User::factory()->create();
    $job = Job::factory()->forUser($owner)->silenced()->create();
    $newUntil = now()->addDays(3)->startOfSecond();

    Livewire::actingAs($owner)
        ->test(JobDetail::class, ['job' => $job])
        ->set('silenceUntil', $newUntil->toDateTimeLocalString())
        ->set('silenceReason', 'Extended works');

    $job->refresh();
    expect($job->silenced_until->startOfSecond()->equalTo($newUntil))->toBeTrue()
        ->and($job->silence_reason)->toBe('Extended works');
});

it('deletes the job when the owner confirms and redirects to home', function () {
    $owner = User::factory()->create();
    $job = Job::factory()->forUser($owner)->create();

    Livewire::actingAs($owner)
        ->test(JobDetail::class, ['job' => $job])
        ->call('delete')
        ->assertRedirect(route('home'));

    expect(Job::find($job->id))->toBeNull();
});

it('forbids a stranger from viewing someone elses personal job', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $job = Job::factory()->forUser($owner)->create();

    $this->actingAs($stranger)
        ->get(route('jobs.show', $job))
        ->assertForbidden();
});

it('shows recent check-ins on the detail page', function () {
    $owner = User::factory()->create();
    $job = Job::factory()->forUser($owner)->create();
    $job->recordCheckIn('203.0.113.42');

    $this->actingAs($owner)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('203.0.113.42');
});

it('edits the job via the openEdit flyout flow', function () {
    $owner = User::factory()->create();
    $job = Job::factory()->forUser($owner)->create(['name' => 'Old name']);

    Livewire::actingAs($owner)
        ->test(JobDetail::class, ['job' => $job])
        ->call('openEdit')
        ->set('form.name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    expect($job->fresh()->name)->toBe('New name');
});

it('shows the job name, schedule and check-in URL to the owning user', function () {
    $owner = User::factory()->create();
    $job = Job::factory()->forUser($owner)->withCron('0 2 * * *')->create([
        'name' => 'Nightly backup',
    ]);

    $this->actingAs($owner)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Nightly backup')
        ->assertSee('0 2 * * *')
        ->assertSee($job->check_in_token);
});
