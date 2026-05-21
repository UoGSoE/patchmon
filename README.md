# Patchmon

A watchdog for server patching. Teams register the servers they look
after, each server has an expected patching cadence and a grace
period, and Patchmon emails the team if a server goes past its
window without being patched.

## What it does

Patchmon replaces a shared spreadsheet with something that will
actually nag you when patching slips. Each server belongs to a team
and has a cadence (monthly, quarterly, twice-yearly or yearly) and a
grace period (days, weeks or months). When the server is patched,
something pings its unique record-patch URL — a one-line curl in a
cron job, a Puppet exec, an SCCM hook, or a sysadmin clicking
"Record a patch" on the server's detail page.

If a server passes its deadline, Patchmon flips it to alerting and
emails the team. The alert is throttled to once a week, so a single
missed message over a holiday won't sit unnoticed. Silencing windows
let you tell Patchmon to stay quiet during planned change freezes
(exam periods, say) for a chosen date range rather than just "until
X".

The home page is the report: a filterable table of every server,
with overdue ones at the top. There are no separate dashboards.

Patchmon is not a patch management tool. It doesn't apply patches,
talk to SCCM or Puppet directly, or track CVEs. It notices when a
server hasn't been patched in too long, and tells someone.

## Stack

- Laravel 13 with Livewire 4 and Flux UI 2
- Sanctum for the JSON API, with `ability:` scoped personal access tokens
- Horizon for queued mail
- Socialite (Keycloak) for SSO; there is no public signup
- Pest 4 for tests, against an in-memory SQLite database
- Scramble for auto-generated OpenAPI docs at `/docs/api`

## Prerequisites

- PHP 8.3 or later
- Composer
- [Lando](https://lando.dev/) (which needs Docker Desktop or
  equivalent)
- A [FluxUI Pro](https://fluxui.dev/) licence. `livewire/flux-pro` is
  in `composer.json` because the UI uses the date-picker and other
  Pro components, so `composer install` will fail without one.

## Getting started

```bash
git clone git@github.com:UoGSoE/patchmon.git
cd patchmon
cp .env.example .env
lando start
lando mfs
```

`lando mfs` is a shortcut for `migrate:fresh` plus seeding. The
seeder gives you a realistic set of teams, around 330 servers, a few
currently-alerting and silenced examples, and a test user to log in
with:

- Username: `admin2x`
- Password: `secret`

SSO is the production login. The seeded user is for local
development only.

## Day-to-day lando commands

```bash
lando artisan ...        # any artisan command
lando composer ...       # composer in the container
lando test               # full Pest suite
lando mfs                # migrate:fresh + seed (rebuild local DB)
```

If you'd rather run things directly on the host without lando, the
usual `php artisan ...` and `composer ...` work too.

## Recording a patch

Every server has a unique record-patch URL containing a UUID token.
The endpoint is deliberately unauthenticated so a one-line cron
entry or a Puppet hook can ping it directly. Sending a personal
access token as a bearer is optional, and only attributes the patch
event to the token's owner.

```bash
# Anonymous (Puppet, SCCM, cron one-liner)
curl -X POST https://patchmon.example.ac.uk/record-patch/<patch_token>

# Attributed, with notes
curl -H "Authorization: Bearer $PATCHMON_API_TOKEN" \
  -H "Content-Type: application/json" \
  -X POST -d '{"notes": "Rebooted twice"}' \
  https://patchmon.example.ac.uk/record-patch/<patch_token>
```

A logged-in user can also record a patch from the server's detail
page in the UI; the event is attributed to them automatically.

The JSON API at `/api/v1/...` covers listing, creating, updating and
silencing servers, gated by Sanctum scopes (`servers:read`,
`servers:write`, `admin:read`, `admin:write`). Once logged in,
`/api/help` has curl and python examples for the common flows, and
`/docs/api` has the auto-generated OpenAPI reference.

## Running tests

```bash
lando test
# or
php artisan test --compact
```

The test environment uses an in-memory SQLite database via
`RefreshDatabase`, so there's no need to migrate or seed before
running the suite. Around 200 feature tests cover the data model, the
UI flows, the API endpoints and the scheduled overdue evaluator.

## Contributing

It's a small project with a small team behind it. If you'd like to
suggest a change:

1. Fork or clone the repository
2. `cp .env.example .env` and `lando start`
3. `lando mfs` to set up the database
4. Write or update a Pest test first, then make the change
5. `lando test` before pushing
6. Open a pull request

We follow Laravel conventions, use `pint` for formatting, and prefer
readable code over clever code. The existing Livewire components and
feature tests are the best guide to the house style.

## Licence

Patchmon is MIT-licensed. See [LICENSE](LICENSE) for the full text.
