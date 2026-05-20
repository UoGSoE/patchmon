# Cronmon

A watchdog for cron-style jobs. Teams register the jobs they care
about, each job gets a unique check-in URL, and Cronmon emails the
responsible person or team if a job doesn't check in within its
schedule plus a grace period.

## What it does

Cronmon sits between your scheduled tasks and the people who need
to know when they break. Every job has a schedule (either a cron
expression or a simple interval like "daily") and a grace period.
When the job runs, it pings its check-in URL; if the ping doesn't
arrive in time, Cronmon raises an alert and keeps re-alerting on
each missed window until the job checks in again.

It's intended for internal IT use: nightly backups, config
snapshots, tape rotation reminders, and the small army of quiet
cron jobs that nobody notices until they stop running.
Authentication is handled by SSO (Keycloak via Socialite), so
there's no public signup.

## Checking in a job

Append a `curl` to whatever your cron entry runs. The full URL is
shown on each job's detail page in Cronmon.

```bash
0 2 * * * /usr/local/bin/nightly-backup && curl -fsS \
  https://cronmon.example.ac.uk/check-in/00000000-0000-0000-0000-000000000000 \
  > /dev/null
```

A few things worth knowing:

- The token in the URL is per-job. Treat it like a webhook URL:
  unguessable, but not a high-stakes secret.
- Use `-fsS` so curl fails the cron command on a non-2xx response.
- The response body is empty; the 200 status is the acknowledgement.

## Prerequisites

- PHP 8.4
- [Lando](https://lando.dev/) for the local development environment
- A [Flux UI](https://fluxui.dev/) Pro licence (Cronmon uses Flux Pro
  components, so the licence is needed to install Composer
  dependencies)
- Composer credentials for the Flux Pro repository in `auth.json`

## Getting started

```bash
cp .env.example .env
lando start
lando mfs        # drop, migrate and seed the dev database
```

The `.env.example` file is pre-configured for Lando, so the
defaults should work without further changes. `lando mfs` runs
the `TestDataSeeder`, which creates a small set of users, teams
and jobs to make the UI usable straight away.

Sign in as **admin2x** / **secret** (admin) or **user2x** / **secret**
(standard user). MailHog is exposed on the Lando proxy so you can
read alert emails locally.

### Useful Lando commands

```bash
lando artisan ...     # any artisan command
lando composer ...    # composer inside the appserver
lando npm ...         # npm inside the node container
lando mfs             # drop + migrate + seed
```

## Self-hosted deployment with Docker Compose

For anyone who'd like to run Cronmon themselves without our internal
Docker Swarm setup, there's a `compose.yml` in the repo root that
brings up the app, scheduler, queue worker, a one-shot migrations
service, MariaDB and Redis. Reverse-proxy / TLS termination is left
to you — the app is published on a configurable host port.

```bash
cp compose.dotenv.example .env       # then edit — see notes inside
docker compose build                 # needs Flux Pro credentials
docker compose up -d
docker compose exec app php artisan cronmon:add-user --admin
```

A few things to know:

- **Flux Pro licence required to build the image.** Cronmon's UI is
  built on [Flux Pro](https://fluxui.dev/), Caleb Porzio's paid
  component library that helps fund Livewire and Flux. Set
  `FLUX_USERNAME` and `FLUX_LICENSE_KEY` in `.env` before
  `docker compose build`. There's no canonical prebuilt image — if
  you're using this app, please chuck a few quid Caleb's way.
- **`APP_KEY` needs generating once.** Run
  `docker compose run --rm app php artisan key:generate --show`
  and paste the result into `.env`, then `up -d`.
- **Mail in demo mode.** If you just want to see the app work without
  wiring up real SMTP, the `mailpit` service in `compose.yml` is
  commented out — uncomment it, set `MAIL_HOST=mailpit` /
  `MAIL_PORT=1025` in `.env`, and alert emails will land in
  Mailpit's web UI on port 8025.
- **SSO is the only sign-in path.** Cronmon is built around Keycloak
  via Socialite. The `KEYCLOAK_*` variables in
  `compose.dotenv.example` need to point at your own realm.
- **Persistence.** MariaDB and Redis use named Docker volumes
  (`cronmon_mariadb`, `cronmon_redis`); back those up if the data
  matters to you.

## Bootstrapping a fresh deploy

On a brand-new install nobody can sign in via SSO yet — there are
no users in the database for the allowlist to match against. Create
the first admin from the command line:

```bash
php artisan cronmon:add-user                    # interactive walkthrough
php artisan cronmon:add-user kmc2y kit.mcauthor@example.ac.uk McAuthor Kit --admin
```

Positional arguments are `<username> <email> <surname> <forenames>`.
The interactive mode asks for the email first and prefills surname
and forenames from it when the address follows the
`forename.surname[.N]@...` pattern, so most of the time you just
confirm the defaults. After that the user can sign in via SSO and
the rest of the admin UI takes over.

## Running tests

The test suite uses Pest and runs against an in-memory SQLite
database via `RefreshDatabase`, so no migration step is needed.

```bash
lando test                              # full suite
lando test --filter=HomePageTest        # one file or test
```

Outside of Lando, `php artisan test --compact` works too if you
have PHP installed locally.

CI runs the suite against both MySQL and PostgreSQL on every push
(see `.github/workflows/`). Production runs on PostgreSQL but a
sibling system uses MySQL, and keeping both happy catches the
occasional dialect difference early.

## Contributing

Pull requests are welcome. The short version:

1. Fork or clone the repository.
2. Follow the [Getting started](#getting-started) steps above.
3. Write a test for your change, make it pass, then run the full
   suite with `lando test`.
4. Run `vendor/bin/pint --dirty` to format any PHP files you've
   touched.
5. Open a pull request describing what changed and why.

Project-specific conventions live in `CLAUDE.md` and the `.ai/`
directory. Worth a skim before making larger changes.

## Licence

Cronmon is released under the [MIT licence](LICENSE).
