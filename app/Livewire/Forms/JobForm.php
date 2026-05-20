<?php

namespace App\Livewire\Forms;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Models\Job;
use Illuminate\Validation\Rule;
use Livewire\Form;

class JobForm extends Form
{
    public ?Job $job = null;

    public string $name = '';

    public ?string $description = null;

    public ?string $location = null;

    public ?string $cron_expression = null;

    public ?string $schedule_interval = null;

    public ?int $schedule_frequency = 1;

    public int $grace_value = 1;

    public string $grace_units = '';

    public ?int $team_id = null;

    public ?string $notification_email = null;

    public ?string $sender_email = null;

    public function setJob(Job $job): void
    {
        $this->job = $job;
        $this->name = $job->name;
        $this->description = $job->description;
        $this->location = $job->location;
        $this->cron_expression = $job->cron_expression;
        $this->schedule_interval = $job->schedule_interval?->value;
        $this->schedule_frequency = $job->schedule_frequency;
        $this->grace_value = $job->grace_value;
        $this->grace_units = $job->grace_units->value;
        $this->team_id = $job->team_id;
        $this->notification_email = $job->notification_email;
        $this->sender_email = $job->sender_email;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'cron_expression' => ['nullable', 'string', 'max:255', 'required_without:schedule_interval'],
            'schedule_interval' => ['nullable', Rule::enum(ScheduleInterval::class), 'required_without:cron_expression'],
            'schedule_frequency' => ['nullable', 'integer', 'min:1', 'required_with:schedule_interval'],
            'grace_value' => ['required', 'integer', 'min:1'],
            'grace_units' => ['required', Rule::enum(GraceUnit::class)],
            'team_id' => [
                'nullable',
                Rule::exists('teams', 'id')->where(fn ($q) => $q->whereIn('id', auth()->user()->teams()->pluck('teams.id'))),
            ],
            'notification_email' => ['nullable', 'email'],
            'sender_email' => ['nullable', 'email'],
        ];
    }

    public function save(): Job
    {
        $this->validate();

        $isCron = ! empty($this->cron_expression);
        $isTeamOwned = ! is_null($this->team_id);

        $job = $this->job ?? new Job;
        $job->fill([
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'cron_expression' => $isCron ? $this->cron_expression : null,
            'schedule_interval' => $isCron ? null : $this->schedule_interval,
            'schedule_frequency' => $isCron ? 1 : ($this->schedule_frequency ?? 1),
            'grace_value' => $this->grace_value,
            'grace_units' => $this->grace_units,
            'team_id' => $isTeamOwned ? $this->team_id : null,
            'user_id' => $isTeamOwned ? null : auth()->id(),
            'notification_email' => $this->notification_email,
            'sender_email' => $this->sender_email,
        ]);

        if (! $job->exists) {
            $job->created_by_user_id = auth()->id();
        }

        $job->save();

        return $job;
    }
}
