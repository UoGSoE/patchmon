<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Livewire\Forms\JobForm;
use App\Models\Job;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class JobDetail extends Component
{
    public Job $job;

    public JobForm $form;

    public bool $silenced = false;

    public string $silenceUntil = '';

    public ?string $silenceReason = null;

    public function mount(Job $job): void
    {
        $this->authorize('view', $job);
        $this->job = $job;
        $this->silenced = $job->isCurrentlySilenced();
        $this->silenceUntil = $job->silenced_until
            ? $job->silenced_until->format('Y-m-d\TH:i')
            : now()->addDay()->format('Y-m-d\TH:i');
        $this->silenceReason = $job->silence_reason;
    }

    public function openEdit(): void
    {
        $this->authorize('update', $this->job);
        $this->form->reset();
        $this->form->resetErrorBag();
        $this->form->setJob($this->job);

        Flux::modal('job-form')->show();
    }

    public function save(): void
    {
        $this->authorize('update', $this->job);
        $this->form->save();

        Flux::modal('job-form')->close();
        Flux::toast('Job updated.', variant: 'success');

        $this->job = $this->job->fresh();
    }

    public function updatedSilenced(bool $value): void
    {
        $this->authorize('update', $this->job);

        if ($value) {
            $this->validate(['silenceUntil' => ['required', 'date', 'after:now']]);
            $this->job->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
            Flux::toast('Job silenced.', variant: 'success');
            return;
        }
        $this->job->unsilence();
        $this->silenceReason = null;
        $this->silenceUntil = now()->addDay()->format('Y-m-d\TH:i');
        Flux::toast('Job unsilenced.', variant: 'success');
    }

    public function updatedSilenceUntil(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->authorize('update', $this->job);
        $this->validate(['silenceUntil' => ['required', 'date', 'after:now']]);
        $this->job->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
    }

    public function updatedSilenceReason(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->authorize('update', $this->job);
        $this->validate(['silenceReason' => ['nullable', 'string', 'max:255']]);
        $this->job->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
    }

    public function delete()
    {
        $this->authorize('delete', $this->job);

        $this->job->delete();

        Flux::toast('Job deleted.', variant: 'success');

        return $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.job-detail', [
            'recentCheckIns' => $this->job->checkIns()
                ->latest('checked_in_at')
                ->limit(20)
                ->get(),
            'checkInUrl' => route('check-in', $this->job->check_in_token),
            'teams' => auth()->user()->teams()->orderBy('name')->get(),
            'intervalOptions' => ScheduleInterval::cases(),
            'graceUnitOptions' => GraceUnit::cases(),
        ]);
    }
}
