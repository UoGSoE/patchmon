<?php

namespace App\Livewire;

use App\Models\Job;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class HomePage extends Component
{
    #[Url(as: 'tab')]
    public $tab = 'mine';

    #[Computed]
    public function myJobs(): Collection
    {
        return $this->sortForListing(
            Job::query()
                ->where('user_id', auth()->id())
                ->with(['team', 'user'])
                ->get()
        );
    }

    #[Computed]
    public function teamJobs(): Collection
    {
        $teamIds = auth()->user()->teams()->pluck('teams.id');

        return $this->sortForListing(
            Job::query()
                ->whereIn('team_id', $teamIds)
                ->with(['team', 'user'])
                ->get()
        );
    }

    #[Computed]
    public function userIsInAnyTeam(): bool
    {
        return auth()->user()->teams()->exists();
    }

    public function render()
    {
        return view('livewire.home-page');
    }

    private function sortForListing(Collection $jobs): Collection
    {
        return $jobs->sort(function (Job $a, Job $b) {
            $aAlerting = $a->alerting_since !== null;
            $bAlerting = $b->alerting_since !== null;

            if ($aAlerting !== $bAlerting) {
                return $aAlerting ? -1 : 1;
            }

            if ($aAlerting) {
                return $b->alerting_since->timestamp <=> $a->alerting_since->timestamp;
            }

            return strcasecmp($a->name, $b->name);
        })->values();
    }
}
