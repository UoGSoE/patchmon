<div class="max-w-4xl">
    <flux:heading size="xl">API examples</flux:heading>
    <flux:text class="mt-2">
        Common things you can do from a terminal or a script, with a personal access token from your
        <flux:link :href="route('settings')" wire:navigate>settings page</flux:link>.
    </flux:text>

    <flux:callout class="mt-6" icon="key" variant="secondary">
        <flux:callout.heading>Set your token once</flux:callout.heading>
        <flux:callout.text>
            Examples below use the environment variable <code>PATCHMON_API_TOKEN</code>. Put this in your shell rc file so every example below works as-is:
        </flux:callout.text>
        <flux:callout.text>
            <pre class="mt-2 text-sm">export PATCHMON_API_TOKEN=&quot;paste-the-token-from-settings-here&quot;</pre>
        </flux:callout.text>
    </flux:callout>

    <flux:tab.group class="mt-6">
        <flux:tabs>
            <flux:tab name="curl">curl</flux:tab>
            <flux:tab name="python">python</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="curl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="sm">Am I authenticated?</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  {{ $baseUrl }}/api/v1/me</pre>
                </div>

                <div>
                    <flux:heading size="sm">List my personal servers</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  "{{ $baseUrl }}/api/v1/servers?scope=mine"</pre>
                </div>

                <div>
                    <flux:heading size="sm">List team servers, filter by name, sort newest-patched first</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  "{{ $baseUrl }}/api/v1/servers?scope=teams&filter[name]=backup&sort=-last_patched_at"</pre>
                </div>

                <div>
                    <flux:heading size="sm">Create a personal interval server (every day, 30 min grace)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  -H "Content-Type: application/json" \
  -X POST \
  -d '{
    "name": "Nightly backup",
    "schedule_interval": "daily",
    "schedule_frequency": 1,
    "grace_value": 30,
    "grace_units": "minutes"
  }' \
  {{ $baseUrl }}/api/v1/servers</pre>
                </div>

                <div>
                    <flux:heading size="sm">Create a team-owned cron server (team_id 1)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  -H "Content-Type: application/json" \
  -X POST \
  -d '{
    "name": "Replication healthcheck",
    "team_id": 1,
    "cron_expression": "*/5 * * * *",
    "grace_value": 2,
    "grace_units": "minutes"
  }' \
  {{ $baseUrl }}/api/v1/servers</pre>
                </div>

                <div>
                    <flux:heading size="sm">Silence a server until tomorrow</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  -H "Content-Type: application/json" \
  -X POST \
  -d '{"silenced_from": "{{ now()->toIso8601String() }}", "silenced_until": "{{ now()->addDay()->toIso8601String() }}", "silence_reason": "Investigating"}' \
  {{ $baseUrl }}/api/v1/servers/42/silence</pre>
                </div>

                <div>
                    <flux:heading size="sm">Get a server's patch event history</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  "{{ $baseUrl }}/api/v1/servers/42/patch-events"</pre>
                </div>

                <div>
                    <flux:heading size="sm">Delete a server</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  -X DELETE \
  {{ $baseUrl }}/api/v1/servers/42</pre>
                </div>
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="python">
            <div class="space-y-6">
                <flux:text size="sm">Examples use <code>requests</code>. Install with <code>pip install requests</code>.</flux:text>

                <div>
                    <flux:heading size="sm">Shared setup</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">import os, requests

BASE = "{{ $baseUrl }}/api/v1"
HEADERS = {"Authorization": f"Bearer {os.environ['PATCHMON_API_TOKEN']}"}</pre>
                </div>

                <div>
                    <flux:heading size="sm">Am I authenticated?</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.get(f"{BASE}/me", headers=HEADERS)
r.raise_for_status()
print(r.json()["user"]["full_name"])</pre>
                </div>

                <div>
                    <flux:heading size="sm">List my personal servers</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.get(f"{BASE}/servers", headers=HEADERS, params={"scope": "mine"})
r.raise_for_status()
for server in r.json()["servers"]["data"]:
    print(server["name"], server["is_overdue"])</pre>
                </div>

                <div>
                    <flux:heading size="sm">List team servers, filter by name, sort newest-patched first</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.get(f"{BASE}/servers", headers=HEADERS, params={
    "scope": "teams",
    "filter[name]": "backup",
    "sort": "-last_patched_at",
})
r.raise_for_status()</pre>
                </div>

                <div>
                    <flux:heading size="sm">Create a personal interval server (every day, 30 min grace)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.post(f"{BASE}/servers", headers=HEADERS, json={
    "name": "Nightly backup",
    "schedule_interval": "daily",
    "schedule_frequency": 1,
    "grace_value": 30,
    "grace_units": "minutes",
})
r.raise_for_status()
server = r.json()["data"]
print("Record-patch URL:", f"{BASE.replace('/api/v1', '')}/record-patch/{server['patch_token']}")</pre>
                </div>

                <div>
                    <flux:heading size="sm">Create a team-owned cron server (team_id 1)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.post(f"{BASE}/servers", headers=HEADERS, json={
    "name": "Replication healthcheck",
    "team_id": 1,
    "cron_expression": "*/5 * * * *",
    "grace_value": 2,
    "grace_units": "minutes",
})
r.raise_for_status()</pre>
                </div>

                <div>
                    <flux:heading size="sm">Silence a server until tomorrow</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">from datetime import datetime, timedelta, timezone

r = requests.post(f"{BASE}/servers/42/silence", headers=HEADERS, json={
    "silenced_from": datetime.now(timezone.utc).isoformat(),
    "silenced_until": (datetime.now(timezone.utc) + timedelta(days=1)).isoformat(),
    "silence_reason": "Investigating",
})
r.raise_for_status()</pre>
                </div>

                <div>
                    <flux:heading size="sm">Get a server's patch event history</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.get(f"{BASE}/servers/42/patch-events", headers=HEADERS)
r.raise_for_status()
for pe in r.json()["patch_events"]["data"]:
    print(pe["patched_at"], pe["source_ip"])</pre>
                </div>

                <div>
                    <flux:heading size="sm">Delete a server</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.delete(f"{BASE}/servers/42", headers=HEADERS)
r.raise_for_status()</pre>
                </div>
            </div>
        </flux:tab.panel>
    </flux:tab.group>

    <flux:callout class="mt-8" icon="book-open" variant="secondary">
        <flux:callout.heading>Looking for the full reference?</flux:callout.heading>
        <flux:callout.text>
            <flux:link :href="$docsUrl" external>Auto-generated OpenAPI docs ↗</flux:link> list every endpoint and field.
            Note: those docs don't currently document the <code>filter[…]</code> query parameters or the <code>scope</code> filter on the servers list endpoint — see the examples above for those.
        </flux:callout.text>
    </flux:callout>
</div>
