<div>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                @if ($server->alerting_since)
                    <flux:icon.exclamation-triangle variant="micro" class="text-red-500" />
                @endif
                <flux:heading size="xl">{{ $server->name }}</flux:heading>
                <flux:badge size="sm" color="{{ $server->os_type->colour() }}">{{ $server->os_type->label() }}</flux:badge>
            </div>
            @if ($server->description)
                <flux:text class="mt-2">{{ $server->description }}</flux:text>
            @endif
            @if ($server->alerting_since)
                <flux:text size="sm" class="mt-1">
                    Due {{ $server->alerting_since->diffForHumans() }}
                </flux:text>
            @endif
        </div>

        <div class="flex gap-2">
            @if (auth()->user()->is_admin)
                <flux:button
                    :href="route('admin.activity.index', ['server' => $server->id])"
                    icon="clock"
                    tooltip="View activity log"
                />
            @endif

            <flux:button wire:click="openEdit" icon="pencil-square" tooltip="Edit" />

            <flux:modal.trigger name="delete-server">
                <flux:button icon="trash" tooltip="Delete" variant="danger" />
            </flux:modal.trigger>
        </div>
    </div>

    @if ($server->isCurrentlySilenced())
        <flux:callout class="mt-4" icon="speaker-x-mark" variant="secondary">
            <flux:callout.heading>Silenced until {{ $server->silenced_until->format('D j M Y') }}</flux:callout.heading>
            @if ($server->silence_reason)
                <flux:callout.text>Reason: {{ $server->silence_reason }}</flux:callout.text>
            @endif
        </flux:callout>
    @elseif ($server->silenced_from && $server->silenced_from->isFuture())
        <flux:callout class="mt-4" icon="clock" variant="secondary">
            <flux:callout.heading>Silence scheduled {{ $server->silenced_from->format('D j M Y') }} – {{ $server->silenced_until->format('D j M Y') }}</flux:callout.heading>
            @if ($server->silence_reason)
                <flux:callout.text>Reason: {{ $server->silence_reason }}</flux:callout.text>
            @endif
        </flux:callout>
    @endif

    <div class="mt-6 grid gap-6 sm:grid-cols-2">
        <div>
            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                <flux:card>
                    <flux:heading size="sm">Schedule</flux:heading>
                    <flux:text class="mt-1">{{ $server->intervalLabel() }}</flux:text>
                    <flux:text size="sm" class="mt-1">{{ $server->grace_value }} {{ strtolower($server->grace_units->label()) }} grace</flux:text>
                </flux:card>

                <flux:card>
                    <flux:heading size="sm">Team</flux:heading>
                    <flux:text class="mt-1">{{ $server->team?->name ?? 'Unassigned' }}</flux:text>
                    @if ($server->createdBy)
                        <flux:text size="sm" class="mt-1">Created by {{ $server->createdBy->full_name ?: $server->createdBy->email }}</flux:text>
                    @else
                        <flux:text size="sm" class="mt-1">Added automatically</flux:text>
                    @endif
                    @if ($server->location)
                        <flux:text size="sm" class="mt-1">Location: {{ $server->location }}</flux:text>
                    @endif
                </flux:card>
            </div>

            <div class="mt-6">
                <flux:heading size="sm">Record-patch URL</flux:heading>
                <flux:text size="sm">Curl this URL when you patch the server. Treat it like a webhook URL.</flux:text>
                <flux:input class="mt-2 font-mono" readonly :value="$recordPatchUrl" copyable />
                <div class="mt-2 flex items-center justify-between gap-2">
                    @if ($server->patch_token_provisioned_at)
                        <flux:text size="sm">Token provisioned {{ $server->patch_token_provisioned_at->format('j M Y') }}</flux:text>
                    @else
                        <flux:text size="sm">Regenerate if the URL may have been exposed.</flux:text>
                    @endif
                    <flux:modal.trigger name="regenerate-token">
                        <flux:button size="sm" variant="subtle" icon="arrow-path">Regenerate</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        </div>
    </div>

    <flux:separator class="mt-6" />

    <div class="mt-6 sm:max-w-1/2">
        <flux:heading size="sm">Record a patch</flux:heading>
        <flux:text size="sm">Logs against your account. Defaults to right now.</flux:text>
        <form wire:submit="recordPatch" class="mt-2 space-y-3">
            <flux:textarea wire:model="patchNotes" label="Notes (optional)" rows="2" placeholder="Anything you'd want a future colleague to know" />
            <flux:input wire:model="patchedAt" type="datetime-local" label="When" />
            <flux:button type="submit" variant="primary" icon="check">Record patch</flux:button>
        </form>
    </div>

    <flux:separator class="mt-6" />

    <flux:fieldset class="mt-6">
        <div class="sm:max-w-1/2 space-y-6">
        <flux:switch wire:model.live="silenced" label="Silenced" description="When silenced, Patchmon won't alert about this server."/>
        @if ($silenced)
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:date-picker
                    mode="range"
                    wire:model.live="silenceUntil"
                    label="Silenced from / until end of"
                    presets="tomorrow next7Days nextMonth next3Months"
                />
                <flux:input wire:model.live.debounce.500ms="silenceReason" label="Reason (optional)" placeholder="Change freeze until term-end" />
            </div>
        @endif
        </div>
    </flux:fieldset>


    <flux:separator class="mt-6" />

    <div class="mt-8">
        <flux:heading size="sm">Recent patches</flux:heading>
        @if ($recentPatchEvents->isEmpty())
            <flux:text class="mt-2">No patches recorded yet.</flux:text>
        @else
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>When</flux:table.column>
                    <flux:table.column>By</flux:table.column>
                    <flux:table.column>Notes</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($recentPatchEvents as $patchEvent)
                        <flux:table.row wire:key="patch-event-row-{{ $patchEvent->id }}">
                            <flux:table.cell>{{ $patchEvent->patched_at->toDayDateTimeString() }} ({{ $patchEvent->patched_at->diffForHumans() }})</flux:table.cell>
                            <flux:table.cell>
                                @if ($patchEvent->patchedBy)
                                    {{ $patchEvent->patchedBy->full_name ?: $patchEvent->patchedBy->email }}
                                @else
                                    <span class="text-zinc-400">Automated</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $patchEvent->notes ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal name="server-form" variant="flyout" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">Edit server</flux:heading>
            <x-patchmon.server-form
                :form="$form"
                :teams="$teams"
                :os-type-options="$osTypeOptions"
                :grace-unit-options="$graceUnitOptions"
                :existing-locations="$existingLocations"
                submit-label="Save changes"
                cancel-action="$flux.modal('server-form').close()"
            />
        </div>
    </flux:modal>

    <flux:modal name="delete-server" variant="flyout" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">Delete this server?</flux:heading>
            <flux:text>
                This removes <strong>{{ $server->name }}</strong> and its patch event history.
                The record-patch URL will stop working.
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:button x-on:click="$flux.modal('delete-server').close()">Cancel</flux:button>
                <flux:button wire:click="delete" variant="danger">Yes, delete</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="regenerate-token" variant="flyout" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">Regenerate the patch token?</flux:heading>
            <flux:text>
                This issues a new record-patch URL for <strong>{{ $server->name }}</strong> and the current
                one stops working. Use it if the token may have been exposed, or after a rebuild so the
                machine can provision a fresh token.
            </flux:text>
            <flux:callout icon="information-circle" variant="secondary">
                <flux:callout.text>
                    A machine running our <code>record_patched.sh</code> will pick up the new token automatically on
                    its next run. Running a custom script? Copy the new record-patch URL afterwards and update it
                    wherever you keep it.
                </flux:callout.text>
            </flux:callout>
            <div class="flex justify-end gap-2">
                <flux:button x-on:click="$flux.modal('regenerate-token').close()">Cancel</flux:button>
                <flux:button wire:click="regenerateToken" variant="danger">Regenerate</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
