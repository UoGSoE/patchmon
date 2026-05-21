# PatchEvent — server patching tracker (forked from cronmon)

A watchdog for server patching, modelled on cronmon. The stakeholder
team manages ~1000 servers across mixed OSes (Linux distros, Windows
Server, FreeBSD). Today they track patching in a shared spreadsheet
with no alerting or reporting. This app replaces that.

Conceptually identical to cronmon: each server has an expected
patching cadence, a check-in is recorded when it's patched, and the
system emails someone if a server goes past its window plus grace.

## What carries across from cronmon unchanged

- Laravel 13 / Livewire 4 / Flux 2 / Sanctum / Horizon stack.
- SSO authentication.
- Team-based ownership and `created_by_user_id` kept separate from
  ownership.
- Silencing as columns (`silenced_until` + `silence_reason`), not a
  model. UI defaults to +24h.
- Append-only patch history (the cronmon `CheckIn` shape).
- Sanctum API with `ability:*` scopes for read/write, and the
  admin sub-namespace pattern.
- UUID-based check-in endpoint as the primary integration point.
- `description`, `location`, `notification_email`, `sender_email`
  columns and the resolution cascade (`resolveNotificationEmail()`,
  `resolveSenderEmail()`).
- All admin CRUD UI patterns.
- Queued markdown mailable for the "overdue" notification.
- `TestDataSeeder` pattern for local dev, with alerting + silenced
  examples baked in.
- Team conventions: TDD, fat models, enums with `label()` /
  `colour()`, Flux UI, sort listings in PHP, `findOrFail`, etc.

## What changes

### Data model

- **`Job` → `Server`**. Table: `servers`. No name clash with the
  Laravel queue scaffold this time, so no `monitored_jobs`-style
  workaround needed.
- **`CheckIn` → `PatchEvent`**. Reads more naturally aloud ("show
  me the patch events for this server").
- **No personal ownership.** `Server` belongs to `Team` only — drop
  the nullable `user_id` column. Keep `created_by_user_id`.
- **`os_type`** enum on `Server`: `Linux | Windows | Other`. The
  team expects to want this more granular later (Ubuntu vs RHEL vs
  etc.) — design the enum so adding cases later is non-disruptive.
- **Drop `cron_expression`** entirely. Drop the
  `dragonmantank/cron-expression` dependency.
- **Schedule collapses to one integer column**: `interval_months`
  (unsigned int). 1 = monthly, 3 = quarterly, 6 = twice-yearly,
  12 = yearly. UI presents friendly labels. Drop cronmon's
  `ScheduleInterval` enum and `schedule_frequency`.
- **Grace period**: keep cronmon's `grace_value` (int) +
  `grace_units` (enum) pattern. Enum cases: `Days | Weeks |
  Months`. Drop `Minutes` and `Hours`.
- **`PatchEvent` adds two columns**:
  - `patched_by` — nullable FK to `users`. Per team convention,
    drop the `_user_id` suffix. Null means automatic /
    unattributed (SCCM, Puppet, etc.).
  - `notes` — nullable text. Free-text tribal knowledge per patch
    ("had to reboot three times before the XYZ service came
    back").

### Schedule evaluation

Calendar-month based. `Server::isOverdue()`:

```
deadline = last_patched_at->addMonths($interval_months) + grace
```

Carbon's `addMonths()` handles month-end edge cases sensibly
(31 Jan + 1 month → 28/29 Feb). If `last_patched_at` is null, fall
back to `created_at` as the reference.

This is rolling-from-last-patch, **not** calendar-aligned. "Once a
month with two weeks grace" means a server patched on 15 May is
fine until 29 June; it does *not* mean "must be patched within
each calendar month". The stakeholder has confirmed this is the
intended semantic.

### Alerting

Simpler than cronmon's per-window re-alert. Weekly throttle only:

- `alerting_since` — first overdue moment. UI badge ("Overdue
  since 12 April"). Cleared on patch.
- `last_alerted_at` — fires if null OR more than 7 days ago.
  Cleared on patch.

The weekly cadence is deliberate: easy to miss a single alert over
Christmas / New Year. Weekly keeps it in someone's inbox until
someone deals with it.

### Manual UI check-in

New for this app — patches can be recorded directly from the web
UI, not just via API. A Livewire form on the server detail page:

- Date/time of patch (default: now).
- Notes (optional).
- `patched_by` auto-populated from `auth()->user()`.

The mailable / overdue eval doesn't care how the `PatchEvent` was
created; both paths funnel through the same `recordPatch()`
helper.

### API check-in endpoint

Single endpoint, optional bearer token for attribution:

- No `auth:sanctum` middleware on the route — the UUID in the
  path is the gate, same as cronmon's unauthed check-in.
- Controller inspects `request()->bearerToken()` manually. If
  present and valid (`PersonalAccessToken::findToken(...)`),
  record the resolved user as `patched_by`. Otherwise null.
- Accepts optional `notes` in the request body.

Puppet / SCCM curls hit it with just the UUID. A sysadmin's
personal patching script can include their token in the header to
get attribution. One endpoint, two attribution behaviours — don't
split it into two routes.

Other API routes (server CRUD, admin) follow cronmon's
`auth:sanctum` + ability-scope pattern unchanged.

### Notifications

One sender email and one destination email per team, with a
per-server override. Same cascade pattern as cronmon.

Context note: Teams webhooks are moving to a paid model, but
emailing a Teams channel still works provided the sender address
is a member of the channel — so plain email is fine for v1.

### Reporting

Like cronmon, the home page *is* the report. Listing of servers,
filterable by overdue / OS / team / silenced. No dashboards, no
charts.

## Decisions deliberately not made yet

- **Caching.** 1000 rows + simple date arithmetic in PHP is fine.
  Don't pre-optimise. Revisit only if profiling shows a problem.
- **Per-distro OS granularity.** Three buckets now; widen the enum
  later when the team has a concrete need.
- **Slack / Teams webhooks.** Email only for v1.
- **Audit trail beyond `PatchEvent`.** The append-only log is
  enough.
- **XOR / ownership guards on `Server`.** Per cronmon's pattern,
  validation lives at the form / API boundary, not the model.

## First moves for the new session

1. `ant` — write the foundation entry, mirroring cronmon's
   `ant foundation` shape.
2. `ait` — create an initiative for "patching tracker MVP" with
   epics for: data model, server CRUD, patch event recording
   (UI + API), alerts, admin, silencing UI, API token management.
3. Port `TestDataSeeder` with overdue / silenced examples adapted
   for servers.
4. Probably faster to prune the copied directory than to
   `laravel new` and port — most files just need renames and a
   handful of files (cron expression eval, `ScheduleInterval`,
   personal-ownership branches) need deletion.
