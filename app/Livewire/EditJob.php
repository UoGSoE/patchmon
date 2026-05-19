<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Livewire\Forms\JobForm;
use App\Models\Job;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class EditJob extends Component
{
    public Job $job;

    public JobForm $form;

    public function mount(Job $job): void
    {
        $this->authorize('update', $job);
        $this->job = $job;
        $this->form->setJob($job);
    }

    public function save()
    {
        $this->form->save();

        Flux::toast('Job updated.', variant: 'success');

        return $this->redirectRoute('jobs.show', $this->job, navigate: true);
    }

    public function render()
    {
        return view('livewire.edit-job', [
            'teams' => auth()->user()->teams()->orderBy('name')->get(),
            'intervalOptions' => ScheduleInterval::cases(),
            'graceUnitOptions' => GraceUnit::cases(),
        ]);
    }
}
