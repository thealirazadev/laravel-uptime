# Contributing to laravel-uptime

Thanks for taking the time to contribute. This is a self-hosted Laravel uptime, SSL-expiry, and
site monitor. The guidelines below keep changes small, tested, and consistent with the existing
codebase. The full engineering rules live in [`docs/rules.md`](docs/rules.md); this file is the
short version plus the exact commands you need.

## Local setup

Requirements: PHP 8.2+ with the `openssl` extension, and Composer. Development runs on SQLite, so
no database server is needed.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

Create a dashboard operator (there is no public registration):

```bash
php artisan uptime:user --name="Your Name" --email="you@example.com"
```

The app needs three processes to run end to end — the web server, a queue worker, and the
scheduler:

```bash
php artisan serve         # http://localhost:8000
php artisan queue:work    # processes checks, alerts, SSL checks, rollups
php artisan schedule:work # dispatches due checks every minute, plus SSL/rollup/prune jobs
```

## Tests and formatting (required before every PR)

Both gates must pass, in this order — they are exactly what CI runs:

```bash
./vendor/bin/pint --test   # PSR-12 formatting check; must report no violations
php artisan test           # full Pest suite (feature + unit), must be green
```

Fix formatting automatically with `./vendor/bin/pint` (no hand-formatting against it). Useful
subsets while iterating:

```bash
php artisan test tests/Feature/IncidentLifecycleTest.php   # a single file
php artisan test --filter=opens_incident_after_threshold   # by test name
```

Tests run against in-memory SQLite via `phpunit.xml`, never your dev database. Do not use real
time, network, or queue in tests: use `Carbon::setTestNow()`/`$this->travel()` for clocks,
`Http::fake()` for check/Slack/webhook requests, `Mail::fake()` for mail, and `Queue::fake()` (or
run jobs synchronously) for dispatch. Factories drive setup — no hand-built rows. See
[`docs/testing.md`](docs/testing.md) for the full strategy.

## Coding standards

- **Framework idioms.** Thin controllers; validation in Form Requests; domain and state-machine
  logic on models (`Monitor::applyCheckResult`) or the small `app/Support` classes; background work
  in Jobs; schedule definitions in `routes/console.php`. No query logic in routes, middleware, or
  Blade.
- **Stack only.** Eloquent, the Laravel HTTP client, Laravel Mail, and database queue/cache/session.
  Do not add notification-channel packages, chart libraries, admin panels, or DTO/repository
  libraries. No new dependency without prior approval.
- **Avoid.** Raw SQL string concatenation; `DB::raw` with user input; driver-specific SQL
  (`DATE()`, `strftime()`) — aggregation must behave identically on SQLite and MySQL; logic in Blade
  beyond display; fat controllers.
- **Concurrency invariants (do not weaken).** The dispatcher claims monitors with a conditional
  `UPDATE` on `next_check_at` and dispatches only on a one-row claim; `RunHttpCheck` keeps
  `WithoutOverlapping` keyed by monitor id; monitor counters/status change only inside
  `applyCheckResult`. Changes here need a maintainer's sign-off.
- **Security.** Validate all input server-side in Form Requests. Never log or echo channel secrets
  or webhook URLs; the public status page never reveals monitor URLs. Blade `{{ }}` escaping
  everywhere; the SVG chart builder is the only sanctioned `{!! !!}` and interpolates only numbers
  it computes.
- **Migrations.** Every schema change is a new migration; never edit an applied one. Ship
  `$fillable`/`$casts` changes in the same commit as the migration that introduces the columns.
- **Style.** Pint owns formatting (PSR-12). Comments explain *why*, not *what*, and only where the
  logic is non-obvious. No emoji anywhere.

## Commits and pull requests

- **Conventional Commits**, short imperative subject: `feat: add ssl expiry threshold warnings`,
  `fix: reset ssl thresholds on renewal`, `test: cover alert dedup`.
- **One discrete change per commit** — a migration, a model, a route, a view, or its test — each in
  a working state. Do not batch multiple features into one commit.
- Keep the diff focused on the change you are making. If you touch something out of scope, split it
  out.
- Before opening a PR, confirm both gates pass locally and fill in the pull request template
  checklist. New behavior ships with tests in the same change.

## Reporting bugs and requesting features

Open an issue using the bug report or feature request template. For anything security-sensitive,
follow [`SECURITY.md`](SECURITY.md) instead of filing a public issue.
