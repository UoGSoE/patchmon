<?php

namespace App\Livewire\Forms;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Events\ActivityOccurred;
use App\Models\Server;
use App\Rules\Fqdn;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServerForm extends Form
{
    public ?Server $server = null;

    public string $name = '';

    public ?string $description = null;

    public ?string $location = null;

    public ?string $os_type = null;

    public ?int $interval_months = 1;

    public int $grace_value = 7;

    public string $grace_units = '';

    public ?int $team_id = null;

    public ?string $notification_email = null;

    public ?string $sender_email = null;

    public bool $is_virtual = false;

    public ?int $netbox_id = null;

    public function setServer(Server $server): void
    {
        $this->server = $server;
        $this->name = $server->name;
        $this->description = $server->description;
        $this->location = $server->location;
        $this->os_type = $server->os_type->value;
        $this->interval_months = $server->interval_months;
        $this->grace_value = $server->grace_value;
        $this->grace_units = $server->grace_units->value;
        $this->team_id = $server->team_id;
        $this->notification_email = $server->notification_email;
        $this->sender_email = $server->sender_email;
        $this->is_virtual = $server->is_virtual;
        $this->netbox_id = $server->netbox_id;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255', new Fqdn,
                Rule::unique('servers', 'name')->ignore($this->server?->id),
            ],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'os_type' => ['required', Rule::enum(OsType::class)],
            'interval_months' => ['required', 'integer', 'min:1'],
            'grace_value' => ['required', 'integer', 'min:1'],
            'grace_units' => ['required', Rule::enum(GraceUnit::class)],
            'team_id' => [
                'required',
                Rule::exists('teams', 'id')->where(fn ($q) => $q->whereIn('id', auth()->user()->teams()->pluck('teams.id'))),
            ],
            'notification_email' => ['nullable', 'email'],
            'sender_email' => ['nullable', 'email'],
            'is_virtual' => ['boolean'],
            'netbox_id' => [
                'nullable', 'integer', 'min:1',
                Rule::unique('servers', 'netbox_id')
                    ->where('is_virtual', $this->effectiveIsVirtual() ? 1 : 0)
                    ->ignore($this->server?->id),
            ],
        ];
    }

    public function save(): Server
    {
        $this->name = strtolower(trim($this->name));

        $this->validate();

        $server = $this->server ?? new Server;

        $server->fill([
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'os_type' => $this->os_type,
            'interval_months' => $this->interval_months,
            'grace_value' => $this->grace_value,
            'grace_units' => $this->grace_units,
            'team_id' => $this->team_id,
            'notification_email' => $this->notification_email,
            'sender_email' => $this->sender_email,
            'is_virtual' => $this->effectiveIsVirtual(),
            'netbox_id' => $this->netbox_id,
        ]);

        if ($server->created_by_user_id === null) {
            $server->created_by_user_id = auth()->id();
        }

        $server->save();

        ActivityOccurred::dispatch(
            auth()->id(),
            $server->id,
            $server->wasRecentlyCreated ? 'Created the server' : 'Updated the server',
            request()->ip(),
        );

        return $server;
    }

    /**
     * NetBox is authoritative for is_virtual on synced rows: while netbox_id is set,
     * the form can't change it, so the stored value wins. Clearing netbox_id first
     * re-enables editing. This is the value that will actually be persisted, so both
     * validation and save() rely on it.
     */
    private function effectiveIsVirtual(): bool
    {
        return $this->server && $this->netbox_id !== null
            ? $this->server->is_virtual
            : $this->is_virtual;
    }
}
