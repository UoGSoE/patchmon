<?php

use App\Jobs\SyncNetboxServers;
use Illuminate\Support\Facades\Bus;

it('dispatches the netbox sync job', function () {
    Bus::fake();

    $this->artisan('patchmon:sync-netbox')->assertSuccessful();

    Bus::assertDispatched(SyncNetboxServers::class);
});
