# Technical Overview

Last updated: 2026-05-22

## What This Is

A watchdog for server patching: teams register servers with an expected
patching cadence, each server records patch events, and Patchmon emails
the team if a server goes past its window plus grace period.

## Stack

- PHP 8.3+ / Laravel 13
- Livewire 4 + Flux UI 2 (Free + Pro) for the UI
- Sanctum 4 for API auth (ability-scoped personal access tokens)
- Horizon 5 for queued mail
- Socialite 5 + `socialiteproviders/keycloak` for SSO
- Scramble for auto-generated OpenAPI (`/docs/api`)
- `spatie/laravel-query-builder` for filterable API listings
- Pest 4 against in-memory SQLite (`RefreshDatabase`)

## Directory Structure

```
app/
  Console/Commands/          AddUser, PatchmonEvaluate (the scheduled evaluator)
  Enums/                     GraceUnit, OsType
  Http/
    Controllers/
      PatchEventController   Unauthenticated /record-patch/{token} endpoint
      Api/V1/                MeController, ServersController, TeamsController
      Api/V1/Admin/          Teams, TeamMembers, Users, ApiTokens
    Middleware/              EnsureUserIsAdmin (alias: 'admin')
  Jobs/RecordPatchEvent      Queue job behind the public record-patch endpoint
  Livewire/                  HomePage, ServerDetail, MySettings, ApiHelp, AdminDashboard
    Admin/                   Teams, TeamDetail, Users, ApiTokens
    Forms/                   Form objects used by Livewire components
  Mail/ServerOverdueNotification   Queued markdown mailable
  Models/                    Server, Team, User, PatchEvent
  Policies/                  ServerPolicy, TeamPolicy
routes/
  web.php                    Livewire pages + the record-patch endpoint
  api.php                    /api/v1/... scoped by Sanctum abilities
  sso-auth.php               Keycloak SSO login/callback
  console.php                Schedules patchmon:evaluate daily at 08:40
database/
  factories/                 ServerFactory has states: forTeam, withInterval,
                             withGrace, silenced, alerting, overdue
  seeders/TestDataSeeder     Local-dev seeder (~330 servers, mixed states)
```

## Domain Model

```
User  ←→  Team  ──→  Server  ──→  PatchEvent
                       │
                       └─→ createdBy: User (separate from team ownership)
                       └─→ patch_token (UUID, used in /record-patch/{token})
```

- `User` belongsToMany `Team` (no personal servers).
- `Team` hasMany `Server`; holds default `notification_email` and `sender_email`.
- `Server` belongs to exactly one `Team`; `created_by_user_id` is tracked separately.
- `PatchEvent` is an append-only log; `patched_by` is nullable (null = automated).

### Key Server fields

- `interval_months` — integer (1, 3, 6, 12 — no cron expressions).
- `grace_value` + `grace_units` (`GraceUnit` enum: Days/Weeks/Months).
- `last_patched_at`, `alerting_since`, `last_alerted_at` — alert state.
- `silenced_from` / `silenced_until` / `silence_reason` — server-level only, no model.
- `notification_email`, `sender_email` — optional override of the team defaults.
- `patch_token` — UUID set in `booted()`, used as the public record-patch URL.

### Enums (`App\Enums`)

- `OsType` — Linux, Windows, Other. Each has `label()` and `colour()`.
- `GraceUnit` — Days, Weeks, Months. Has `addTo(Carbon, int)` using
  `addMonthsNoOverflow` so 31 Jan + 1 month resolves to end-of-Feb.

## Authorization

| Role     | How it's determined            | Can do                                      |
|----------|--------------------------------|---------------------------------------------|
| Admin    | `users.is_admin = true`        | Everything (admin pages, all servers/teams) |
| Staff    | `users.is_staff = true`        | IT-staff scoped UI (the normal app users)   |
| API user | Sanctum token with abilities   | Scoped by `servers:read/write`, `admin:read/write` |

- Middleware aliases (`bootstrap/app.php`): `admin`, `abilities`, `ability`.
- Policies: `ServerPolicy`, `TeamPolicy`.
- Public exception: `record-patch/*` is excluded from CSRF.

## Routes Overview

### Web (`routes/web.php`)

| Route                            | Handler                       | Access  |
|----------------------------------|-------------------------------|---------|
| `POST /record-patch/{token}`     | `PatchEventController`        | Public  |
| `GET /`                          | `Livewire\HomePage`           | auth    |
| `GET /servers/{server}`          | `Livewire\ServerDetail`       | auth    |
| `GET /settings`                  | `Livewire\MySettings`         | auth    |
| `GET /api/help`                  | `Livewire\ApiHelp`            | auth    |
| `GET /admin/...`                 | `Livewire\Admin\*`            | admin   |

### API (`routes/api.php`, all `auth:sanctum`)

| Endpoint                                  | Ability         |
|-------------------------------------------|-----------------|
| `GET /api/v1/me`, `GET /api/v1/teams`     | (token only)    |
| `GET /api/v1/servers[...]`                | `servers:read`  |
| `POST/PATCH/DELETE /api/v1/servers[...]`  | `servers:write` |
| `GET /api/v1/admin/...`                   | `admin:read` + admin user |
| `POST/PATCH/DELETE /api/v1/admin/...`     | `admin:write` + admin user |

## Key Business Logic

| Location                                        | Purpose                                      |
|-------------------------------------------------|----------------------------------------------|
| `Server::recordPatch()`                         | The single funnel for recording a patch (UI + API + job) |
| `Server::deadline()` / `isOverdue()`            | When the next patch is due (last_patched + interval + grace) |
| `Server::isCurrentlySilenced()`                 | Server-level silencing check                 |
| `Server::resolveNotificationEmail()/SenderEmail()` | Falls back to the team's defaults         |
| `Console\Commands\PatchmonEvaluate`             | Daily sweep — alerts overdue servers with a weekly throttle |
| `Mail\ServerOverdueNotification`                | Queued markdown mailable used by the evaluator |
| `Jobs\RecordPatchEvent`                         | Queue job behind `/record-patch/{token}`     |
| `Livewire\HomePage::sortForListing()`           | PHP-side sort (DB null-ordering differs across MySQL/Postgres) |

## Testing

- Framework: Pest 4 (feature tests dominate; very few unit tests).
- Database: in-memory SQLite via `RefreshDatabase`.
- Run: `php artisan test --compact` or `lando test`.
- Fixtures: factory states on `ServerFactory` — `forTeam`, `withInterval`,
  `withGrace`, `silenced`, `alerting`, `overdue`.
- Coverage: data model, Livewire UI flows, API endpoints, the scheduled
  evaluator, the public record-patch endpoint, policies, and the seeder.

## Local Development

```bash
lando start              # boot containers
lando mfs                # migrate:fresh + TestDataSeeder
lando artisan ...        # any artisan command
lando test               # Pest suite
```

Seeded admin user: `admin2x` / `secret` (local only — SSO is the production login).

## Notable Conventions (project-specific)

- Sort server lists in PHP, not SQL — see `HomePage::sortForListing()`.
- Funnel patch recording through `Server::recordPatch()` — never insert
  PatchEvents directly.
- No model-level guards on `Server`; validation lives at the form / API boundary.
- Use Eloquent only — no raw SQL, no DB facade.
- `User` has `username`, `forenames`, `surname` (no `name` column).
  Full name via the `fullName` accessor.
