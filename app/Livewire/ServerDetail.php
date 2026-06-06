<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Livewire\Forms\ServerForm;
use App\Models\Server;
use App\Jobs\RecordPatchEvent;
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

    public $silenceUntil = '';

    public ?string $silenceReason = null;

    public ?string $patchNotes = null;

    public string $patchedAt = '';

    public function mount(Server $server): void
    {
        $this->authorize('view', $server);
        $this->server = $server;
        $this->silenced = $server->isCurrentlySilenced();
        $from = $server->silenced_from ?? now();
        $until = $server->silenced_until ?? now()->addDay();
        $this->silenceUntil = $from->format('Y-m-d').'/'.$until->format('Y-m-d');
        $this->silenceReason = $server->silence_reason;
        $this->patchedAt = now()->format('Y-m-d\TH:i');
    }

    public function recordPatch(): void
    {
        $this->authorize('update', $this->server);

        $this->validate([
            'patchNotes' => ['nullable', 'string', 'max:1000'],
            'patchedAt' => ['required', 'date', 'before_or_equal:now'],
        ]);

        RecordPatchEvent::dispatchSync(
            $this->server->id,
            null,
            Carbon::parse($this->patchedAt),
            auth()->id(),
            $this->patchNotes,
        );

        $this->patchNotes = null;
        $this->patchedAt = now()->format('Y-m-d\TH:i');
        $this->server = $this->server->fresh();

        Flux::toast('Patch recorded.', variant: 'success');
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
            $this->validateSilenceWindow();
            [$from, $until] = $this->silenceWindowFromPickedRange();
            $this->server->silenceBetween($from, $until, $this->silenceReason);
            Flux::toast('Server silenced.', variant: 'success');

            return;
        }
        $this->server->unsilence();
        $this->silenceReason = null;
        $this->silenceUntil = now()->format('Y-m-d').'/'.now()->addDay()->format('Y-m-d');
        Flux::toast('Server unsilenced.', variant: 'success');
    }

    public function updatedSilenceUntil(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->authorize('update', $this->server);
        $this->validateSilenceWindow();
        [$from, $until] = $this->silenceWindowFromPickedRange();
        $this->server->silenceBetween($from, $until, $this->silenceReason);
    }

    public function updatedSilenceReason(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->authorize('update', $this->server);
        $this->validate(['silenceReason' => ['nullable', 'string', 'max:255']]);
        [$from, $until] = $this->silenceWindowFromPickedRange();
        $this->server->silenceBetween($from, $until, $this->silenceReason);
    }

    public function delete()
    {
        $this->authorize('delete', $this->server);

        $this->server->delete();

        Flux::toast('Server deleted.', variant: 'success');

        return $this->redirectRoute('home', navigate: true);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function silenceWindowFromPickedRange(): array
    {
        [$startDate, $endDate] = $this->silenceRangeDates();

        return [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function silenceRangeDates(): array
    {
        $value = $this->silenceUntil;

        if (is_array($value)) {
            $start = $value['start'] ?? null;
            $end = $value['end'] ?? $start;

            return [$start, $end];
        }

        if (is_string($value) && str_contains($value, '/')) {
            [$start, $end] = explode('/', $value, 2);

            return [trim($start) ?: null, trim($end) ?: null];
        }

        $only = is_string($value) && $value !== '' ? $value : null;

        return [$only, $only];
    }

    private function validateSilenceWindow(): void
    {
        $this->validate([
            'silenceUntil' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    [$startDate, $endDate] = $this->silenceRangeDates();

                    if (! $startDate || ! $endDate || ! strtotime($startDate) || ! strtotime($endDate)) {
                        $fail('Pick a start and end date.');

                        return;
                    }

                    $start = Carbon::parse($startDate)->startOfDay();
                    $end = Carbon::parse($endDate)->endOfDay();

                    if ($end->isPast()) {
                        $fail('End date must be today or later.');

                        return;
                    }

                    if ($end->lessThan($start)) {
                        $fail('End date must be on or after start date.');
                    }
                },
            ],
        ]);
    }

    public function render()
    {
        return view('livewire.server-detail', [
            'recentPatchEvents' => $this->server->patchEvents()
                ->with('patchedBy')
                ->latest('patched_at')
                ->limit(20)
                ->get(),
            'recordPatchUrl' => route('record-patch', $this->server->patch_token),
            'teams' => auth()->user()->teams()->orderBy('name')->get(),
            'osTypeOptions' => OsType::cases(),
            'graceUnitOptions' => GraceUnit::cases(),
            'existingLocations' => Server::query()
                ->whereNotNull('location')
                ->distinct()
                ->orderBy('location')
                ->pluck('location'),
        ]);
    }
}
