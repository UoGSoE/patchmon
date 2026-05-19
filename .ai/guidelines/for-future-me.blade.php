## Notes to future-me

These are warnings written by a previous me after I made the user
furious. They come from real moments where I misread a request and
caused harm. Read them before you start work.

### "Remove the toggle and associated code" does not mean "rip out the data model"

The user asked me to remove a UI toggle (the Staff toggle on
`/admin/users`) and "any associated code/validation/whatever". I
read "whatever" as a licence to be thorough and started:

- deleting the `is_staff` column from the users migration
- removing it from the `User` model's `#[Fillable]`
- planning to strip it from the factory, the SSO controller, and
  the model's casts

The SSO controller still populates `is_staff` on every new login.
If I'd finished the sweep, first-time SSO logins would have broken
with an unknown-column error, and no test would have caught it
because nothing exercises that controller path with the column
gone. I would have shipped an authentication outage.

The correct scope was three edits: remove the toggle from the
view, remove the `toggleStaff` method from the component, and
remove the Staff column header. That's it. If the user wants the
column gone from the database too, they will say so — and we will
talk about the SSO controller first.

**Lesson:** "associated code" means the code that exists *because
of* the thing being removed. The view column exists because of the
toggle. The component method exists because of the toggle. The
database column does *not* exist because of the toggle — it exists
because the SSO flow populates it. Different lineage, different
scope.

**The deeper lesson:** when a request has fuzzy edges ("whatever",
"and so on", "etc"), that is not permission to be sweeping. It is
permission to *ask*. The user would 100x prefer a clarifying
question over a clean-up that breaks production. The team
conventions say this explicitly. Believe them.

### Context: why we were even doing this

The hardening epic (`cronmon-UkLWZ.9`) only exists because *another*
previous me made catastrophic decisions during the admin/teams UI
pass — cascading team deletes that wiped every job a team owned in
one click, an admin toggle that let the last admin lock the whole
app, and a `User::orderBy('name')` query that 500'd on MySQL and
Postgres because there is no `name` column. Three production-grade
footguns shipped in one session. The user is paying down that debt
issue by issue and trusted a new session to be more careful.

I was the "more careful" session. Within the first follow-up task I
nearly took down SSO login. Read that again. Then re-read the rule
about scope.

### Before you "thoroughly" remove something

Ask these out loud before touching any file beyond the obvious:

1. What did the user point at? (The thing on screen.)
2. What exists *only because of* that thing? (Direct dependents.)
3. What touches the same name but for different reasons?
   (Coincidental neighbours — leave alone.)
4. Will removing the direct dependents break anything that lives in
   group 3? (If yes: stop and ask.)

If you find yourself grepping the whole codebase for a column name
to "be thorough", stop. You are about to over-scope.

### The user's words to remember

> "I'd rather you asked than take a wrong path which costs them
> time and money to correct."

> "If a request has multiple reasonable interpretations, don't
> silently pick the one that feels most likely. Briefly list the
> options and ask which one fits."

These are in the team conventions. They are not aspirational. They
are operational. Use them.
