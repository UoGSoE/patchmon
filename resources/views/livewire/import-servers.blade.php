<div class="mx-auto max-w-3xl space-y-8 p-6">
    <div>
        <flux:heading size="xl">Bulk import servers</flux:heading>
        <flux:text class="mt-1">
            Upload an Excel sheet with one server per row. Columns:
            <flux:badge>server name</flux:badge>, <flux:badge>os</flux:badge>, <flux:badge>last patched</flux:badge> (optional).
        </flux:text>
    </div>

    @if ($this->lastImportSummary)
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>Imported {{ $this->lastImportSummary['created'] }} servers into {{ $this->lastImportSummary['team_name'] }}.</flux:callout.heading>
            <flux:callout.text>
                @if ($this->lastImportSummary['duplicates'] > 0 || $this->lastImportSummary['invalid'] > 0)
                    Skipped {{ $this->lastImportSummary['duplicates'] }} already-known and {{ $this->lastImportSummary['invalid'] }} invalid row(s).
                @endif
                Upload another sheet below if you have more to add.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-4">
        <flux:select wire:model.live="team_id" label="Team">
            <flux:select.option value="">Choose a team…</flux:select.option>
            @foreach ($teams as $team)
                <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->chosenTeamIsForeign)
            <flux:text size="sm" class="text-amber-700 dark:text-amber-400">
                Just so you know — you're not a member of that team. Carry on if that's intentional.
            </flux:text>
        @endif

        <flux:select wire:model="interval_months" label="Patch every">
            <flux:select.option value="1">Monthly</flux:select.option>
            <flux:select.option value="3">Quarterly</flux:select.option>
            <flux:select.option value="6">Twice-yearly</flux:select.option>
            <flux:select.option value="12">Yearly</flux:select.option>
        </flux:select>

        <div>
            <flux:heading size="sm">Grace period</flux:heading>
            <flux:text size="sm">How late can it be before we alert?</flux:text>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="grace_value" type="number" min="1" />
                <flux:select wire:model="grace_units">
                    @foreach ($graceUnitOptions as $unit)
                        <flux:select.option value="{{ $unit->value }}">{{ $unit->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <flux:heading size="sm">Spreadsheet</flux:heading>
        <flux:checkbox
            wire:model.live="firstRowIsHeader"
            label="First row is a heading"
            description="Untick this if your first row is already a server."
        />
        <flux:file-upload
            wire:model="file"
            accept=".xlsx"
            class="mt-2"
        >
            <flux:file-upload.dropzone
                heading="Drop your .xlsx file here"
                text="Or click to choose from your computer"
            />
        </flux:file-upload>
    </div>

    @if ($previewed)
        <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">Preview</flux:heading>

            <flux:text>
                <strong>{{ count($validRows) }}</strong> server(s) ready to create.
            </flux:text>

            @if (count($invalidRows) > 0)
                <details>
                    <summary class="cursor-pointer text-amber-700 dark:text-amber-400">
                        {{ count($invalidRows) }} row(s) need attention — click to see them
                    </summary>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($invalidRows as $row)
                            <li>Row {{ $row['row'] }}: <code>{{ $row['name'] ?: '(empty)' }}</code> — {{ $row['reason'] }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if (count($duplicateRows) > 0)
                <details>
                    <summary class="cursor-pointer text-zinc-600 dark:text-zinc-400">
                        Skipping {{ count($duplicateRows) }} server(s) that already exist — click to see them
                    </summary>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($duplicateRows as $row)
                            <li>Row {{ $row['row'] }}: <code>{{ $row['name'] }}</code></li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if (count($validRows) > 0)
                <flux:button wire:click="confirm" variant="primary">
                    Create {{ count($validRows) }} server(s)
                </flux:button>
            @endif
        </div>
    @endif
</div>
