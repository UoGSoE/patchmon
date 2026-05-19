## Cronmon — Project Overview

Cronmon is a watchdog for cron-style jobs. Teams and users register
jobs that check in on a schedule; if a job doesn't check in within
its expected window plus a grace period, Cronmon emails someone.
This is a rewrite of an internal app that fell out of maintenance.
Laravel 13 / Livewire 4 / Flux 2. IT staff only — no students, no
public users, SSO already configured.

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
entry deliberately and the ADRs are kept up to date — read them
before forming opinions about how things work.

### Data model at a glance

- `User` — has personal jobs (`user_id`), can be in multiple teams.
- `Team` — has jobs (`team_id`), `belongsToMany` users via
  `team_user` pivot.
- `Job` — belongs to **exactly one** of a team or a user.
  `created_by_user_id` is always set separately from ownership so
  team-mates can see who added a job.
- `CheckIn` — append-only log of every ping a job sends.

### Footgun: the Job table is `monitored_jobs`, not `jobs`

Laravel's queue scaffold owns the `jobs` table (used by Horizon's
`failed_jobs` / `job_batches` housekeeping even though we run the
queue on Redis). The Cronmon `Job` model points at `monitored_jobs`
via `protected $table`. When writing migrations / FK constraints
that reference jobs, use `constrained('monitored_jobs')`. See ADR
`cronmon-XKtxA` for the full reasoning.

### Load-bearing decisions worth knowing before you touch the code

Use `ant search <topic>` to read these in full. Listed here so you
know they exist:

- **Silencing is column-based, not a model** — `silenced_until`
  (nullable datetime) on `User`, `Team`, `Job`. UI defaults the
  picker to +24h. No history; if you need history, add an audit
  table without disturbing this. See `cronmon-VYQvH`.
- **Grace period is `grace_value` + `grace_units` enum** — not flat
  minutes. The UI shows "2 hours" not "120 minutes". The evaluator
  converts via `GraceUnit::toMinutes()`. See `cronmon-VYQvH`.
- **Alerts repeat per missed window**, not one-per-outage. There are
  *two* state columns: `alerting_since` (first awol moment, UI
  badge) and `last_alerted_at` (throttle). Both clear on check-in.
  See `cronmon-Ed6UZ` — and note that `cronmon-sYVTv` is partly
  superseded by it.
- **No model-level XOR guards on `Job`** — the "exactly one of
  team/user" and "exactly one of cron/interval" rules live in the
  form layer (Job CRUD epic), not in the model. Trust the caller;
  validate at the boundary. See `cronmon-XKtxA`.

### Conventions specific to this codebase (beyond CLAUDE.md)

- **Sort job listings in PHP, not in the DB.** Null-ordering varies
  across MySQL / Postgres / SQLite and we run CI against the first
  two. See `App\Livewire\HomePage::sortForListing()` for the
  pattern.
- **Resolution helpers live on `Job`**:
  `resolveNotificationEmail()`, `resolveSenderEmail()`,
  `isCurrentlySilenced()`. They handle the team/user fallback
  chain. Use these from mailables and the evaluator — don't
  re-implement the cascade.
- **Factory states are the right way to set up test fixtures.**
  `Job::factory()->forTeam($t)`, `->forUser($u)`,
  `->withCron('...')`, `->silenced()`, `->alerting()`. Same for
  `User::factory()->silenced()` and `Team::factory()->silenced()`.
- **`TestDataSeeder` is the local-dev seeder.** Includes alerting
  and silenced examples for UI work.

### What exists today (May 2026)

Foundation data model is in. Home page (read-only listing with
Flux tabs, sorted alerting-first) is in. CRUD, watchdog, alerts,
silencing UI, admin UI, and the Sanctum management API are all
captured as epics under initiative `cronmon-UkLWZ` but not yet
built. Run `ait list --tree` for the current map.
