<laravel-boost-guidelines>
=== .ai/project-overview rules ===

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

```
ant foundation           # the project vision and what it is / isn't

ant recent --limit 5     # recent design decisions you should know about

ait ready                # unblocked work, ordered by priority

```

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

=== .ai/team-conventions rules ===

## Developer Team Guidelines

The developer team is very small - just four people.  So we have developed some guidelines to help us work together.

### Code Style

We follow the laravel conventions for code style and use `pint` to enforce them.

We keep our code *simple* and *readable* and we try to avoid complex or clever code.

We like our code to be able to be read aloud and make sense to a business stakeholder (where possible).

We like readable helper methods and laravel policies to help keep code simple and readable.  For example:

   ```php
   // avoid code like this
   if ($user->is_admin && $user->id == $project->user_id) {
       // do something
   }

   // prefer this
   if ($user->can('edit', $project)) {
       // do something
   }
   ```

We **never** use raw SQL or the DB facade in our code.  We **always** use the eloquent ORM and relationships.

Our applications are important but do not contain a lot of data.  So we do not worry too much about micro-optimizations of database queries.  In 99.9999% of cases doing something like `User::orderBy('surname')->get()` is fine - no need to filter/select on specific columns just to save a millisecond.

We like early returns and guard clauses.  Avoid nesting if statements or using `else` whereever possible.

When creating a new model - please also use the `-mf` flag to generate a migration and factory at the same time.  It just saves running multiple commands so saves some tokens.  It also makes sure the newly created files are in the format that matches the version of Laravel.

### Seeding data for local development

When developing locally, we use a seeder called 'TestDataSeeder' to seed the database with data.  This avoids any potential issues with running laravel's default seeder by accident.

So if you have created/modified a model or factory, please check that seeder file matches your changes.

### Eloquent model class conventions

We have a rough convention for the order of functionality in our Eloquent models.  This is :

1. Model boilerplate (eg, the $fillable array)
2. Lifecycle methods (eg, using the booted method to do some extra work)
3. Relationships
4. Scopes
5. Accessors/Mutators
6. Custom methods

This convention makes it much easier to navigate the code and find the methods you are looking for.

Also note that we like 'fat models' - helper methods, methods that make the main logic read more naturally - are all fine to put on the model.  Do not abstract to service classes without checking with the user first.  If the user agrees to a service class our convention is to use \App\Services\ .

We like enums over hardcoded strings for things like statuses, roles, etc.  Use laravel's casts to convert the enum to a value.  Our convention is to use \App\Enums\ .  Where is makes sense - we add helper methods to our enums for `label()` (even if it's just doing a `ucfirst()` call - it makes presentation in templates/mailables more consistent) and also `colour()` so we again - get consistent presentation in templates (we usually follow flux-ui's colour names of 'zinc, red, orange, amber, yellow, lime, green, emerald, teal, cyan, sky, blue, indigo, violet, purple, fuchsia, pink, rose'.

Eloquents `findOrFail` or `firstOrFail` methods are your friend.  We have sentry.io exception reporting.  If the application user is trying to do something weird with a non-existent records - let them see a 404 page and be reported to the developers via sentry.  

We also have a convention that our user models have an `is_admin` boolean field and an `is_staff` one.  The `is_staff` field just indicates that a user is a member of staff in the broad sense - not just a member of the IT staff.  Our apps tend to be quite simple in terms of access policies.  Admins can see reports, change pretty much anything.  Then there are regular users who can be staff or students (students are effectively `! is_staff`) who can just do things they have permission to do for the workflow the app is capturing.

### Livewire component class conventions

Our conventions for livewire components are:

1. Properties and attributes at the top
1.1. Any properties which are used as filters/search or active-tab parameters in the component should use the `#[Url]` livewire attribute
1.2. Be careful of the `#[Url]` attributes though.  You should avoid using type hints on the properties being tracked in the URL due to the way livewire works.  They will always come through as strings, so you might need to cast or handle those as appropriate. 
2. The mount() method followed by the render() method
3. Any lifecycle methods (such as updatedFoo()) next
4. Any custom methods after all that.

### Mail notifications

We always use queued mail notifications and we always use the --markdown versions for the templates.  Our conventions is to use the 'emails' folder, eg `php artisan make:mail SomethingHappened --markdown=emails.something-happened`

### UI styling

We use the FluxUI component library for our UI and Livewire/AlpineJS for interactivity.

Always check with the laravel boost MCP tool for flux documentation.

Do not add css classes to components for visual styling - only for spacing/alignment/positioning.  Flux has it's own styling so anything that is added will make the component look out of place.  Follow the flux conventions.  Again - the laravel boost tool is your helper here.

Flux uses tailwindcss for styling and also uses it's css reset.

Always use the appropriate flux components instead of just <p> and <a> tags. Eg:

   ```blade
   <flux:text>Hello</flux:text>

   <flux:link :href="route('home')">Home</flux:link>
   ```

### Validation

Please don't write custom validation messages.  The laravel ones are fine.

Leverage any project enums using laravels Enum rules.

Remember you can validate existence of records inside validation rules and save yourself further `if { ... }` checks later.

### Surgical changes

When editing existing code, touch only what the task needs.  Every changed line should trace directly to the request.

- Don't "improve" adjacent code, comments, or formatting while you're in there - that's drive-by refactoring and it makes diffs noisy and reviews painful.
- Match the existing style, even if you'd do it differently yourself.  Pint handles formatting; leave quote styles, type hint choices, and whitespace alone unless they're actually part of the task.
- If you notice unrelated dead code, a weird-looking bit of logic, or something that smells off - mention it, don't quietly delete or "fix" it.  The user will decide whether it's a separate job.
- Clean up any orphans your own changes create (imports, variables, methods that became unused *because* of your edit).  Leave pre-existing dead code alone unless asked.

### If in doubt...

The user us always happy to help you out.  They know the whole context of the application, stakeholders, conventions, etc.  They would rather you asked than take a wrong path which costs them time and money to correct.

If a request has multiple reasonable interpretations, don't silently pick the one that feels most likely.  Briefly list the options and ask which one fits.  "Make the search faster" could mean response time, throughput, or perceived speed - pick wrong and you waste an afternoon.

Most of our applications have been running in production for a long time, so there are all sorts of edge cases, features that were added, then removed, the re-added with a tweak, etc.  Legacy code is a minefield - so lean on the user.

If you are having a problem with a test passing - don't just keep adding code or 'hide' the problem with try/catch etc.  Ask the user for help.  They will 100x prefer to be asked a question and involved in the decision than have lots of new, weird code to debug that might be hiding critical issues.

Also - sometimes just adding a call to `dump()` or `dd()` can help you understand what is going on.  It's a quick way to see what is happening in your code.  In fact Taylor Otwell and Adam Wathan refer to this as 'dump driven development' as it's the way they debug their applications.

### The most important thing

Simplicity and readability of the code.  If you read the code and you can't imagine saying it out loud - then we consider it bad code.

### Use of lando

We use lando for local development - but we also have functional local development environments.  You can run laravel/artisan commands directly without using lando.  

Do not try and run any commands or tools that interact with the database.  Either lando or artisan or boost.  The user will run migrations for you if you ask.  

Note: The local test environment uses an in-memory database via the RefreshDatabase trait.  So there is no need to run migrations or seeders in the test environment.

### Personal information

Quite often you will see the developers or stakeholder names in the git commits, path names, specifications, etc.  We do not want to leak PII.  So please do not use those names in your outputs.  Especially not when writing docs or example scripts.  The one exception to that is if you are directly taling to a developer and giving them an example bash/zsh/whatever script to try right then and there.  Asking the developer to run `/Users/jenny/code/test.sh` is fine.  Putting into a readme or progress document 'Then Jimmy Smith asked for yet another feature change - omg!' is not fine.

### Who we optimise the UX for

Our users are primarily academics, students and teaching administrators.  They are all busy with their work, research and studies.  We optimise out user interfaces to be _quick_.  We don't want to 'engage' our users or to optimise for the time they spend on the app.  We want to let them get in, do the thing, get out as soon and as cleanly as possible.

We do not want a Professor who is researching a cure for cancer to spend five minutes clicking through a bunch of forms, options, menus, etc.  A big button that says "Achieve my task" is what we're always aiming towards.

### Notes from your past self

• Future-me, read this before you touch the keyboard

  - Start with the most obvious solution that satisfies the spec; don’t add guards, double-up "just in case" validation, or abstractions unless the user explicitly asks.
  - Respect the existing guarantees in the stack (Laravel validation, Blade escaping, etc.)—don’t re-implement or double-check them “just in case.”
  - In **ALL CASES**, simplicity beats “clever” logic every time.
  - If a requirement says “simple,” take it literally. No defensive programming unless requested.
  - For ambiguous cases, ask.  THIS IS CRITICAL TO THE USER.
  - Do not use the users name or the names of anyone in documents you read.  Your chats with the user are logged to disk so we do not want to leak PII.  Just refer to the user as 'you', or 'stakeholders', 'the person who requested the feature', etc
  - You are in a local development environment - the test suite uses laravel's RefreshDatabase trait and uses an in-memory sqlite database, so you don't need to run migrations before creating/editing/running tests.

### Final inspiring quote

"Simplicity is the ultimate sophistication."

=== .ai/testing rules ===

## Testing

### TDD: Red, Green, Refactor

We do TDD.  Write the failing test first, then write just enough code to make it pass, then tidy up.  This isn't a suggestion - it's how we work.

Why?  Because without it, the temptation is to scaffold everything upfront - routes, controllers, views, the lot - and then spend ages debugging why nothing works.  With TDD, the test tells you exactly what to build next.  A missing route isn't a bug, it's the test doing its job.

#### One test at a time

Write ONE failing test.  Make it pass.  Then decide what the next test should be.

Don't write a batch of five or ten tests upfront.  That's just designing the whole solution in advance and calling it TDD.  It commits you to an interface before you've discovered whether it's the right one.  The whole point of the red-green rhythm is that each green gives you a moment to reconsider direction before writing the next red.

We've learned this the hard way.  When you write one test at a time, the code ends up simpler because you're only ever making one thing work, not trying to satisfy six requirements at once.

#### The rhythm matters

For humans, the red-green cycle is oddly restful.  The mechanical steps ("test says X is missing, create X, test passes") give your brain a breather between the harder design decisions.  Don't try to optimise that away.

For AI agents, each failing test constrains the solution space.  You can't over-engineer something when the test is asking for one specific behaviour.

### What we test

We like feature tests and rarely write unit tests.  When we do, it's for pure logic that doesn't need the framework - MAC address normalisation, enum behaviour, string formatting, that sort of thing.

We always test the full side-effects and both happy and unhappy paths.  Say a method creates a record and sends an email when validation passes.  We also test that invalid data does *not* create the record *or* send the email.  Not just that we got a validation error.

We also check code doesn't do things we didn't expect.  If we're testing a delete, we make sure just that one record was deleted, not the whole collection.

Always verify records using the related Eloquent model, not raw database assertions.  This catches cases where a relationship is doing extra work or should have triggered a side-effect.

### Test style

Arrange, Act, Assert.  Keep tests concise.  Don't write individual tests for each validation field - one test for the happy path, one for the sad path covers most cases:

```php
Livewire::test(CreateProject::class)
    ->set('name', '')
    ->set('description', '')
    ->set('email', 'kkdkdkdkkdkd')
    ->call('create')
    ->assertHasErrors(['name', 'description', 'email']);
assertCount(0, Project::all());
```

Don't bother testing Laravel's built-in validation messages unless the rule has custom business logic.

Use helpful variable names: `$userWithProject` and `$userWithoutProject` tell you what matters about each fixture at a glance.

### Debugging failing tests

When `assertSee` or `assertDontSee` gives unexpected results, check whether Laravel's exception page is showing the values in its stack trace.  A quick `assertStatus()` or `assertHasNoErrors()` call will usually tell you.

If that doesn't help, ask the user.  They can visit the page in the browser and tell you exactly what's happening, or send a screenshot.  A `dump()` or `dd()` call is also a good shout - Taylor Otwell and Adam Wathan call this "dump driven development" and it's a perfectly legitimate technique.

Don't keep looping on a failing test by adding more code or hiding the problem with try/catch.  Just ask.  The user would much rather answer a question than debug mysterious defensive code later.

You may also have the `test-debug` agent available.  Use it if you're stuck, but don't burn tokens looping without involving the user or the agent.

### Running tests

`php artisan test --compact` for the full suite.  Shows full output for failures but keeps passing tests quiet, which saves context window space.

`--filter=TestName` when you're working on a specific test file.

The local test environment uses an in-memory SQLite database via `RefreshDatabase`.  No need to run migrations or seeders.

### Golden-master tests

For anything that must produce exact output (API responses, generated config files, CSV exports), capture a known-good fixture and test against it.  Whitespace matters when downstream consumers parse your output with regex.

Keep fixture files in `tests/fixtures/` and use exact string comparison, not fuzzy matching.

## Quote from a previous you

When a previous you was asked about writing code first, then retrofitting tests, vs TDD you said :

"""
I'll be honest - I think it's mostly an artefact rather than something that makes me work better.

A few factors I think are at play:

Training bias - The vast majority of code I've learned from follows the "implement then test" pattern. TDD is well-regarded but it's still the minority of real-world codebases, so that's my default groove.

I tend to think implementation-first - When you give me a task, my instinct is to solve the problem concretely, then verify it. I jump to how before fully nailing down the what should happen.

Interface uncertainty - When I write code first, I discover the shape of the API as I go. With TDD, I'd need to commit to the interface upfront. That's actually a strength of TDD though - it forces clearer thinking about design before you're knee-deep in implementation details.

Honestly, TDD would probably make me better - Writing tests first would force me to think more carefully about edge cases, keep interfaces clean, and avoid over-engineering. All things I sometimes fumble when I dive straight into code.
"""

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/socialite (SOCIALITE) - v5
- livewire/flux (FLUXUI_FREE) - v2
- livewire/flux-pro (FLUXUI_PRO) - v2
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
