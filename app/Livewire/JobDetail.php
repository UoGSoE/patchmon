<?php

namespace App\Livewire;

use App\Models\Job;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class JobDetail extends Component
{
    public Job $job;

    public string $silenceUntil = '';

    public ?string $silenceReason = null;

    public function mount(Job $job): void
    {
        $this->authorize('view', $job);
        $this->job = $job;
        $this->silenceUntil = now()->addDay()->format('Y-m-d\TH:i');
    }

    public function silence(): void
    {
        $this->authorize('update', $this->job);

        $this->validate([
            'silenceUntil' => ['required', 'date', 'after:now'],
            'silenceReason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->job->silenceUntil(
            Carbon::parse($this->silenceUntil),
            $this->silenceReason,
        );

        Flux::modal('silence-job')->close();
        Flux::toast('Job silenced.', variant: 'success');
    }

    public function unsilenceJob(): void
    {
        $this->authorize('update', $this->job);

        $this->job->unsilence();

        Flux::toast('Job unsilenced.', variant: 'success');
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
        ]);
    }
}
