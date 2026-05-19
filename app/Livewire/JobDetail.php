<?php

namespace App\Livewire;

use App\Models\Job;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class JobDetail extends Component
{
    public Job $job;

    public function mount(Job $job): void
    {
        $this->authorize('view', $job);
        $this->job = $job;
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
