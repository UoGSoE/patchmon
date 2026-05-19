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

@verbatim
```php
Livewire::test(CreateProject::class)
    ->set('name', '')
    ->set('description', '')
    ->set('email', 'kkdkdkdkkdkd')
    ->call('create')
    ->assertHasErrors(['name', 'description', 'email']);
assertCount(0, Project::all());
```
@endverbatim

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
