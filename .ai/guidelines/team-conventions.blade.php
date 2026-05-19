## Developer Team Guidelines

The developer team is very small - just four people.  So we have developed some guidelines to help us work together.

### Code Style

We follow the laravel conventions for code style and use `pint` to enforce them.

We keep our code *simple* and *readable* and we try to avoid complex or clever code.

We like our code to be able to be read aloud and make sense to a business stakeholder (where possible).

We like readable helper methods and laravel policies to help keep code simple and readable.  For example:

@verbatim
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
@endverbatim

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

@verbatim
   ```blade
   <flux:text>Hello</flux:text>

   <flux:link :href="route('home')">Home</flux:link>
   ```
@endverbatim

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

