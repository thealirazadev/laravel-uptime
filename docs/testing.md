# Testing — laravel-uptime

## Strategy

- **Automated first, manual second.** Every feature ships with tests in the same commit series.
  Manual QA (the phase checklists in `docs/phases.md`) covers what automation can't reach
  honestly: real SMTP/Slack delivery, long-running scheduler/worker behavior, and visual/a11y
  review of the dashboard and status pages.
- **Pest on PHPUnit.** Feature tests for HTTP behavior, jobs, and commands; unit tests for pure
  logic. `RefreshDatabase` everywhere; tests run against in-memory SQLite via `phpunit.xml`
  overrides regardless of the local dev driver. Never against the dev database.
- **No real time, network, or queue in tests.** `Carbon::setTestNow()`/`$this->travel()` for
  clocks; `Http::fake()` for check requests, Slack, and webhooks; `Mail::fake()` for mail;
  `Queue::fake()` when asserting dispatches, or run jobs synchronously when asserting effects.
  The one true-network seam, certificate fetching, is isolated in `Support/Ssl` and faked via
  container binding.
- **Factories** for every model drive setup; no hand-built rows in tests.

### What gets unit tests

- `Monitor::applyCheckResult` state machine: counter math, `first_failed_at`, threshold edges
  (N-1 vs N), `unknown → up` silence, flap sequences producing no transitions, at most one open
  incident.
- SSL threshold selection: crossings, once-per-threshold monotonicity, renewal reset, implicit
  expiry threshold, no-threshold-crossed silence.
- Webhook signature: known body + secret → expected `sha256=` hex; no header without a secret.
- `Support/Chart`: bucket selection and SVG numeric output for known rollup fixtures (no
  pixel-perfect assertions — data mapping only).

### What gets feature tests

- Auth: login success/failure/throttle, logout, `auth` middleware on every dashboard route,
  `uptime:user` command.
- Monitor/channel/group CRUD: validation matrix (URL scheme, interval set, threshold and timeout
  ranges, per-type channel config, slug rules), pause behavior, delete cascades and group
  null-on-delete, secret masking on channel edit.
- Dispatcher: due selection, the atomic claim (a second dispatch pass on the same due set
  dispatches nothing), `next_check_at` advancement, paused monitors skipped.
- `RunHttpCheck`: ok/fail matrix via `Http::fake` (2xx match, wrong status, timeout, connection
  error, keyword present/missing), one check row per run, overlap-lock drop of a duplicate job,
  no exception on failing targets.
- Incident lifecycle end to end: open with correct `started_at`, timeline events, close on
  confirmed recovery.
- Alerts: exactly one dispatch per channel on open and on close; zero on continued failure;
  disabled/detached channels skipped; sender failure → retry then `alert_failed` event, other
  channels unaffected; test-alert action.
- SSL job: expiry stored, warning routed to the monitor's channels, connection failure logged
  without alerting.
- Rollups and retention: hourly/daily aggregates match seeded raw rows, idempotent re-run,
  prune removes exactly the out-of-window rows, uptime derivation.
- Status pages: HTML and JSON content correctness, contract shape, 404 for unknown/non-public
  slug (identical responses), cache behavior, throttling, no URL leakage.

### Manual QA only

- A real end-to-end alert to a real Slack webhook and a real mailbox (or Mailpit).
- `schedule:work` + two `queue:work` processes observed over several intervals.
- Phase 3 MySQL sanity pass (migrations + core flows against MySQL 8).
- Accessibility/visual review against `docs/design.md`.

## Exact commands

```bash
# Full test suite
php artisan test

# Equivalent via Pest directly
./vendor/bin/pest

# A single file
php artisan test tests/Feature/IncidentLifecycleTest.php

# Filter by test name
php artisan test --filter=opens_incident_after_threshold

# Formatting check (must pass; PSR-12 via Pint)
./vendor/bin/pint --test

# Auto-fix formatting
./vendor/bin/pint
```

First-time setup:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

Running the app locally (three processes):

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
```

## Definition of "done" for a feature

A feature is not done until all of the following pass, in order:

1. `./vendor/bin/pint --test` — no style violations.
2. `php artisan test` — full suite green, new tests included.
3. The feature's manual checklist items in `docs/phases.md` pass.

After creating or editing files, run build/tests and fix all errors before reporting done. One
commit per feature, in the order listed in `docs/phases.md`.
