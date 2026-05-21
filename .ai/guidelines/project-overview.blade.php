## Patchmon — Project Overview

Patchmon is a watchdog for server patching. Teams register servers
with an expected patching cadence (e.g. monthly, quarterly, yearly);
each server records a patch event when it's patched, and if a server
goes past its window plus a grace period, Patchmon emails the owning
team. This is a fork of an internal cron-monitoring app called
Cronmon, reshaped for a different stakeholder team that today tracks
patching in a spreadsheet with no alerting and no reporting.
Laravel 13 / Livewire 4 / Flux 2 / Sanctum / Horizon. IT staff only —
no students, no public users, SSO already configured.

### First thing every session

If you've just landed in this project, run these three commands
before doing anything else:

@verbatim
```
ant foundation           # the project vision and what it is / isn't
ant recent --limit 5     # recent design decisions you should know about
ait ready                # unblocked work, ordered by priority
```
@endverbatim

`ant` is the notebook for *why*; `ait` is the issue tracker for
*what*. Both live at the git root. The user wrote the foundation
entry deliberately — read it before forming opinions about how
things work.

### Data model at a glance

- `User` — `belongsToMany` teams via `team_user` pivot. No personal
  servers; users only "own" servers through team membership.
- `Team` — `hasMany` servers, `belongsToMany` users. Holds the
  default `notification_email` and `sender_email` for its servers.
- `Server` — belongs to **exactly one** team. `created_by_user_id`
  is tracked separately from ownership so team-mates can see who
  added a server. Has an `os_type` enum (Linux / Windows / Other),
  an `interval_months` integer for cadence, and a
  `grace_value` + `grace_units` enum pair for the grace window.
- `PatchEvent` — append-only log of every patch. Carries optional
  `patched_by` (nullable FK to users — null means automatic /
  unattributed, e.g. SCCM or Puppet) and free-text `notes`.

The `servers` table is `servers` — no name clash with Laravel's
queue scaffold this time (which uses `jobs`), so no `protected $table`
override is needed.

### Load-bearing decisions worth knowing before you touch the code

Most of these were inherited from Cronmon and confirmed for Patchmon
in the foundation entry. Use `ant foundation` for the full vision.

- **Silencing is column-based, not a model** — `silenced_until`
  (nullable datetime) and `silence_reason` (nullable text) on
  `Server` only. UI defaults the picker to +24h. No history; if you
  need history, add an audit table without disturbing this. **No
  team-level or user-level silencing** — that was Cronmon's pattern
  and was deliberately removed.
- **Grace period is `grace_value` + `grace_units` enum** — units are
  `Days | Weeks | Months` (no minutes/hours; those don't make sense
  at patching cadences). The UI shows "2 weeks" not "14 days". The
  enum has an `addTo(Carbon, int)` helper that uses
  `addMonthsNoOverflow` so 31 Jan + 1 month resolves to 28/29 Feb.
- **Schedule is a single `interval_months` integer** — 1 = monthly,
  3 = quarterly, 6 = twice-yearly, 12 = yearly. No cron expressions;
  Cronmon's `cron_expression` / `ScheduleInterval` /
  `schedule_frequency` and the `dragonmantank/cron-expression`
  package are all gone.
- **Alerts use a weekly throttle, not per-window re-alerts.** Two
  state columns: `alerting_since` (first awol moment, UI badge) and
  `last_alerted_at` (throttle gate — fires if null or ≥ 7 days ago).
  Both clear on patch. Weekly is deliberate: a single alert is easy
  to miss over a holiday, weekly keeps it in someone's inbox.
- **No model-level guards on `Server`.** Trust the caller; validate
  at the form / API boundary.

### Conventions specific to this codebase (beyond CLAUDE.md)

- **Sort server listings in PHP, not in the DB.** Null-ordering
  varies across MySQL / Postgres / SQLite and we run CI against the
  first two. See `App\Livewire\HomePage::sortForListing()` for the
  pattern.
- **Resolution helpers live on `Server`**:
  `resolveNotificationEmail()`, `resolveSenderEmail()`,
  `isCurrentlySilenced()`. The email helpers fall back to the team's
  values; `isCurrentlySilenced()` only checks the server's own
  column (server-level-only silencing). Use these from mailables and
  the evaluator — don't re-implement.
- **Funnel patch recording through `Server::recordPatch()`.** Both
  the manual UI form (`ServerDetail`) and the API endpoint
  (`PatchEventController` → `RecordPatchEvent` queue job) call this.
  Signature: `recordPatch(?User $patchedBy, ?string $notes,
  ?string $sourceIp, ?Carbon $at)`. It writes the PatchEvent, stamps
  `last_patched_at`, and clears `alerting_since` / `last_alerted_at`.
- **Factory states are the right way to set up test fixtures.**
  `Server::factory()->forTeam($team)`, `->withInterval(3)`,
  `->withGrace(2, GraceUnit::Weeks)`, `->silenced()`, `->alerting()`,
  `->overdue()`.
- **`TestDataSeeder` is the local-dev seeder.** Seeds ~330 servers
  across six teams with a mix of OS types, cadences, and a handful
  of alerting + silenced examples for UI work.

### What exists today (May 2026)

The data model epic (`patchmon-UkLWZ.1`) is done — schema, models,
enums, factories, and a realistic seeder are all in. Most of the
Livewire UI, API controllers, admin pages, and mailable came across
from Cronmon during the rename and reshape passes and *technically
work*, but they still reflect Cronmon's flow in places. The
remaining epics under `patchmon-UkLWZ` cover the Patchmon-flavoured
behavioural changes:

- **Server CRUD** — interval-months UI, OS-type select, team-only
  ownership flow in the Livewire form.
- **Patch event recording (UI + API)** — the bearer-token-optional
  endpoint and the Livewire form on the server detail page.
- **Alerts** — confirm the weekly-throttle behaviour with a proper
  Patchmon mailable (the basic logic already runs in
  `PatchmonEvaluate`).
- **Silencing UI**, **Admin**, **API token management** — same
  shape as Cronmon, repurposed.

Run `ait list --tree` for the current map.
