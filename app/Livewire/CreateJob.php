<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Livewire\Forms\JobForm;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CreateJob extends Component
{
    public JobForm $form;

    public function mount(): void
    {
        $this->form->grace_units = GraceUnit::Minutes->value;
    }

    public function save()
    {
        $this->form->save();

        Flux::toast('Job created.', variant: 'success');

        return $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.create-job', [
            'teams' => auth()->user()->teams()->orderBy('name')->get(),
            'intervalOptions' => ScheduleInterval::cases(),
            'graceUnitOptions' => GraceUnit::cases(),
        ]);
    }
}
