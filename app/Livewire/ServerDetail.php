<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Livewire\Forms\ServerForm;
use App\Models\Server;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ServerDetail extends Component
{
    public Server $server;

    public ServerForm $form;

    public bool $silenced = false;

    public string $silenceUntil = '';

    public ?string $silenceReason = null;

    public function mount(Server $server): void
    {
        $this->authorize('view', $server);
        $this->server = $server;
        $this->silenced = $server->isCurrentlySilenced();
        $this->silenceUntil = $server->silenced_until
            ? $server->silenced_until->format('Y-m-d\TH:i')
            : now()->addDay()->format('Y-m-d\TH:i');
        $this->silenceReason = $server->silence_reason;
    }

    public function openEdit(): void
    {
        $this->authorize('update', $this->server);
        $this->form->reset();
        $this->form->resetErrorBag();
        $this->form->setServer($this->server);

        Flux::modal('server-form')->show();
    }

    public function save(): void
    {
        $this->authorize('update', $this->server);
        $this->form->save();

        Flux::modal('server-form')->close();
        Flux::toast('Server updated.', variant: 'success');

        $this->server = $this->server->fresh();
    }

    public function updatedSilenced(bool $value): void
    {
        $this->authorize('update', $this->server);

        if ($value) {
            $this->validate(['silenceUntil' => ['required', 'date', 'after:now']]);
            $this->server->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
            Flux::toast('Server silenced.', variant: 'success');

            return;
        }
        $this->server->unsilence();
        $this->silenceReason = null;
        $this->silenceUntil = now()->addDay()->format('Y-m-d\TH:i');
        Flux::toast('Server unsilenced.', variant: 'success');
    }

    public function updatedSilenceUntil(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->authorize('update', $this->server);
        $this->validate(['silenceUntil' => ['required', 'date', 'after:now']]);
        $this->server->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
    }

    public function updatedSilenceReason(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->authorize('update', $this->server);
        $this->validate(['silenceReason' => ['nullable', 'string', 'max:255']]);
        $this->server->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
    }

    public function delete()
    {
        $this->authorize('delete', $this->server);

        $this->server->delete();

        Flux::toast('Server deleted.', variant: 'success');

        return $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.server-detail', [
            'recentPatchEvents' => $this->server->patchEvents()
                ->latest('patched_at')
                ->limit(20)
                ->get(),
            'recordPatchUrl' => route('record-patch', $this->server->patch_token),
            'teams' => auth()->user()->teams()->orderBy('name')->get(),
            'intervalOptions' => ScheduleInterval::cases(),
            'graceUnitOptions' => GraceUnit::cases(),
            'existingLocations' => Server::query()
                ->whereNotNull('location')
                ->distinct()
                ->orderBy('location')
                ->pluck('location'),
        ]);
    }
}
