<div>
    <flux:heading size="xl">API and CLI stuff</flux:heading>
    <flux:text class="mt-2">
        Common things you can do from a terminal or a script, with a personal access token from your
        <flux:link :href="route('settings')" wire:navigate>settings page</flux:link>.
    </flux:text>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
        <div>
        <flux:callout icon="key" variant="secondary">
            <flux:callout.heading>Two tokens, two purposes</flux:callout.heading>
            <flux:callout.text>
                Your <strong>personal access token</strong> (<code>PATCHMON_API_TOKEN</code>) authenticates you against the JSON API: listing servers, silencing, etc.
                Each server also has its own <strong>patch token</strong> — a UUID baked into a per-server <code>/record-patch/&lt;token&gt;</code> URL. That endpoint is unauthenticated by design so Puppet, SCCM or a one-liner cron job can ping it. Adding your personal access token as a bearer is optional and only attributes the patch event to you.
            </flux:callout.text>
            <flux:callout.text>
                All the API examples assume you've set the <code>PATCHMON_API_TOKEN</code> environment variable, eg:
                <pre class="mt-2 text-sm">export PATCHMON_API_TOKEN=&quot;paste-the-token-from-settings-here&quot;</pre>
            </flux:callout.text>
        </flux:callout>
        </div>
        <div>
        <flux:card>
            <flux:heading size="lg">First-run helper script</flux:heading>
            <flux:text class="mt-2">
                For freshly-built servers, the helper scripts are the no-faff option: run one when you patch and
                it sorts itself out. On first run it claims this machine's patch token by hostname, saves it locally,
                and records the patch. Every run after that just records the patch. The copies below already point at
                this Patchmon install.
            </flux:text>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button icon="arrow-down-tray" :href="route('scripts.record-patch')">
                    Linux
                </flux:button>
                <flux:button icon="arrow-down-tray" :href="route('scripts.record-patch-ps')">
                    Windows
                </flux:button>
            </div>

            <flux:text size="sm" class="mt-4">
                It keeps its settings in <code>/etc/patchmon.env</code> on Linux (root-only) or
                <code>C:\ProgramData\Patchmon\patchmon.env</code> on Windows (Administrators only). Puppet, SCCM or a
                build profile can drop this in ahead of time; the token line is filled in automatically on first run:
            </flux:text>
            <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">
PATCHMON_URL="{{ $baseUrl }}"
PATCHMON_TOKEN="filled-in-on-first-run"</pre>

            <flux:text size="sm" class="mt-4">
                Each server's token is a one-time claim. If a machine is rebuilt, or you think a token has been exposed,
                regenerate it from the server's page in Patchmon and the script will re-enrol on its next run.
            </flux:text>
        </flux:card>
        </div>
    </div>

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
                    <flux:heading size="sm">Record a patch (unauthenticated — Puppet / SCCM / cron one-liner)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -X POST {{ $baseUrl }}/record-patch/&lt;patch_token&gt;</pre>
                </div>

                <div>
                    <flux:heading size="sm">Record a patch with attribution and notes</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  -H "Content-Type: application/json" \
  -X POST \
  -d '{"notes": "Had to reboot twice"}' \
  {{ $baseUrl }}/record-patch/&lt;patch_token&gt;</pre>
                </div>

                <div>
                    <flux:heading size="sm">List every server I can see</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  {{ $baseUrl }}/api/v1/servers</pre>
                </div>

                <div>
                    <flux:heading size="sm">List Linux servers, sort newest-patched first</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  "{{ $baseUrl }}/api/v1/servers?filter[os_type]=linux&sort=-last_patched_at"</pre>
                </div>

                <div>
                    <flux:heading size="sm">Restrict to one team</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  "{{ $baseUrl }}/api/v1/servers?filter[team_id]=1"</pre>
                </div>

                <div>
                    <flux:heading size="sm">Create a server (team-owned, monthly patching, 7 days grace)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  -H "Content-Type: application/json" \
  -X POST \
  -d '{
    "name": "fileserver-prod-02.chem.example.ac.uk",
    "team_id": 1,
    "os_type": "linux",
    "interval_months": 1,
    "grace_value": 7,
    "grace_units": "days"
  }' \
  {{ $baseUrl }}/api/v1/servers</pre>
                </div>

                <div>
                    <flux:heading size="sm">Silence a server between two dates (e.g. exam window)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  -H "Content-Type: application/json" \
  -X POST \
  -d '{"silenced_from": "{{ now()->toIso8601String() }}", "silenced_until": "{{ now()->addDay()->toIso8601String() }}", "silence_reason": "Investigating"}' \
  {{ $baseUrl }}/api/v1/servers/42/silence</pre>
                </div>

                <div>
                    <flux:heading size="sm">Get this server's patch history</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800"># 1. Find this machine — the "id" is in the JSON that comes back
curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  "{{ $baseUrl }}/api/v1/servers?filter[name]=$(hostname -f)"

# 2. Use that id to fetch the patch history
curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  "{{ $baseUrl }}/api/v1/servers/&lt;id-from-step-1&gt;/patch-events"</pre>
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
from datetime import datetime, timedelta, timezone

ROOT = "{{ $baseUrl }}"
BASE = f"{ROOT}/api/v1"
HEADERS = {"Authorization": f"Bearer {os.environ['PATCHMON_API_TOKEN']}"}</pre>
                </div>

                <div>
                    <flux:heading size="sm">Am I authenticated?</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.get(f"{BASE}/me", headers=HEADERS)
r.raise_for_status()
print(r.json()["user"]["full_name"])</pre>
                </div>

                <div>
                    <flux:heading size="sm">Record a patch (unauthenticated — Puppet / SCCM / cron one-liner)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">requests.post(f"{ROOT}/record-patch/&lt;patch_token&gt;").raise_for_status()</pre>
                </div>

                <div>
                    <flux:heading size="sm">Record a patch with attribution and notes</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.post(
    f"{ROOT}/record-patch/&lt;patch_token&gt;",
    headers=HEADERS,
    json={"notes": "Had to reboot twice"},
)
r.raise_for_status()</pre>
                </div>

                <div>
                    <flux:heading size="sm">List every server I can see</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.get(f"{BASE}/servers", headers=HEADERS)
r.raise_for_status()
for server in r.json()["servers"]["data"]:
    print(server["name"], server["is_overdue"])</pre>
                </div>

                <div>
                    <flux:heading size="sm">List Linux servers, sort newest-patched first</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.get(f"{BASE}/servers", headers=HEADERS, params={
    "filter[os_type]": "linux",
    "sort": "-last_patched_at",
})
r.raise_for_status()</pre>
                </div>

                <div>
                    <flux:heading size="sm">Create a server (team-owned, monthly patching, 7 days grace)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.post(f"{BASE}/servers", headers=HEADERS, json={
    "name": "fileserver-prod-02.chem.example.ac.uk",
    "team_id": 1,
    "os_type": "linux",
    "interval_months": 1,
    "grace_value": 7,
    "grace_units": "days",
})
r.raise_for_status()</pre>
                </div>

                <div>
                    <flux:heading size="sm">Silence a server between two dates (e.g. exam window)</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">r = requests.post(f"{BASE}/servers/42/silence", headers=HEADERS, json={
    "silenced_from": datetime.now(timezone.utc).isoformat(),
    "silenced_until": (datetime.now(timezone.utc) + timedelta(days=1)).isoformat(),
    "silence_reason": "Investigating",
})
r.raise_for_status()</pre>
                </div>

                <div>
                    <flux:heading size="sm">Get this server's patch history</flux:heading>
                    <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">import socket

# Find this machine by its hostname (filter[name] is a partial match)
r = requests.get(f"{BASE}/servers", headers=HEADERS, params={
    "filter[name]": socket.getfqdn(),
})
r.raise_for_status()
server = r.json()["servers"]["data"][0]

# Now fetch its patch history
r = requests.get(f"{BASE}/servers/{server['id']}/patch-events", headers=HEADERS)
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

    <flux:separator class="my-8" />

    <flux:heading size="lg">Prometheus metrics</flux:heading>
    <flux:text class="mt-2">
        Current estate figures are exposed at <code>{{ $baseUrl }}/metrics</code> in Prometheus text format, for
        scraping into Grafana. The endpoint needs a static bearer token: set <code>PATCHMON_METRICS_TOKEN</code> in the
        environment and have Prometheus present it as the scrape credential. Until a token is set the endpoint returns
        <code>503</code>; a missing or wrong token gets <code>403</code>.
    </flux:text>

    <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <flux:heading size="sm">Prometheus scrape config</flux:heading>
            <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">scrape_configs:
  - job_name: patchmon
    scheme: {{ $metricsScheme }}
    metrics_path: /metrics
    authorization:
      type: Bearer
      credentials: "your-PATCHMON_METRICS_TOKEN"
    static_configs:
      - targets: ["{{ $metricsHost }}"]</pre>
        </div>

        <div>
            <flux:heading size="sm">Metrics exposed</flux:heading>
            <flux:text size="sm" class="mt-1">
                All gauges. The per-team ones carry a <code>team</code> label — sum them in Grafana for an estate total.
            </flux:text>
            <pre class="mt-2 overflow-x-auto rounded bg-zinc-100 p-3 text-xs dark:bg-zinc-800">patchmon_servers_total{team="…"}              # monitored servers
patchmon_servers_overdue{team="…"}            # overdue, not silenced
patchmon_servers_silenced{team="…"}           # currently silenced
patchmon_servers_patched_recently{team="…"}   # patched in the last 30 days
patchmon_servers_never_checked_in             # live servers (any team) never patched</pre>
        </div>
    </div>

    <flux:callout class="mt-8" icon="book-open" variant="secondary">
        <flux:callout.heading>Looking for the full reference?</flux:callout.heading>
        <flux:callout.text>
            <flux:link :href="$docsUrl" external>Auto-generated OpenAPI docs ↗</flux:link> list every endpoint and field.
        </flux:callout.text>
    </flux:callout>
</div>
