<?php

use App\Livewire\JobDetail;
use App\Models\Job;
use App\Models\User;
use Livewire\Livewire;

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
