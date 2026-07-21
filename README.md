# laravel-uptime

[![CI](https://github.com/thealirazadev/laravel-uptime/actions/workflows/ci.yml/badge.svg)](https://github.com/thealirazadev/laravel-uptime/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A self-hosted uptime, SSL-expiry, and site monitor built with Laravel. A freelancer or small agency
adds the sites they manage; the app runs scheduled HTTP and certificate checks from queued jobs,
records response times, opens and closes incidents with confirmation thresholds, and sends alerts
over mail, Slack incoming webhooks, or a signed generic webhook. Each monitor group gets a public
status page; operators manage everything from a plain Blade dashboard.

Status: v1 implemented (monitors, overlap-safe queued checks, incident lifecycle, alerts, SSL
expiry, rollups/retention, public status pages).

## Planned stack

- PHP 8.2+ / Laravel 12.x
- Blade + minimal server-rendered SVG charts (no SPA, no JS framework)
- SQLite for local development, MySQL 8.x in production
- Laravel scheduler + database queue driver (no Redis dependency)
- Pest (feature + unit tests) on PHPUnit, Laravel Pint (PSR-12 formatting)

See `docs/` for the PRD, architecture, API contracts, phases, and engineering rules.

## Requirements

- PHP 8.2+ with the `openssl` extension (used for SSL expiry checks)
- Composer
- SQLite for local development; MySQL 8.x in production

## Install

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

`APP_KEY` also encrypts stored alert-channel configuration. Rotating it orphans existing
channel secrets, so keep it stable.

## Run

The app needs three processes: the web server, a queue worker, and the scheduler.

```bash
php artisan serve         # http://localhost:8000
php artisan queue:work    # processes checks, alerts, SSL checks, rollups
php artisan schedule:work # dispatches due checks every minute, plus SSL/rollup/prune jobs
```

In production, run `php artisan schedule:run` every minute from cron and supervise one or more
`php artisan queue:work` processes.

Log in at `/login`, add monitors, alert channels, and groups. Each public group is served at
`/status/{slug}` (with a JSON twin at `/status/{slug}/json`).

## Test

```bash
php artisan test          # full Pest suite
./vendor/bin/pint --test  # formatting check (PSR-12)
```

See `docs/testing.md` for the full testing strategy and additional commands.

## Design decisions

The trade-offs below are the load-bearing ones; the full rationale lives in
`docs/architecture.md` and the dated decisions log in `docs/memory.md`.

- **Database queue, cache, and locks — not Redis.** The queue driver plus the cache/session
  stores all run on the one database, so the whole app needs PHP and a single database and nothing
  else. The `cache_locks` table provides the real atomic locks behind `WithoutOverlapping` and
  `onOneServer`. The trade-off is lower throughput and coarser lock granularity than Redis, which
  is irrelevant at the hundreds-of-monitors scale this targets (a one-minute tick dispatches at
  most `monitor_count` small jobs). Redis was rejected as a *requirement*, not as an option — it
  stays a drop-in config change if a deployment ever outgrows the database.

- **SQLite in dev, MySQL 8 in prod.** Development needs zero services (`php artisan serve` plus a
  file); production gets proper concurrent writes for multiple queue workers. The cost is that
  "two workers race the same row" is only fully exercised on MySQL, so the claim is written as a
  plain conditional `UPDATE` that behaves identically on both engines, and driver-specific SQL
  (`DATE()`, `strftime()`) is banned — rollups bucket in PHP. A single engine for both
  environments was rejected: it would force either services on developers or SQLite into
  production.

- **Blade with server-rendered SVG charts — no JS build.** Charts are inline SVG produced by
  `Support/Chart` from rollup rows; there is no chart library and no npm/Vite build in v1. The
  trade-off is no tooltips or zoom, which is acceptable for "is it up and how slow" charts and
  keeps the dashboard dependency-free and fast to serve. An SPA or JS charting stack was rejected
  as disproportionate for a server-rendered admin tool with one public read-only page.

- **SSL expiry warnings are alerts, not incidents.** A certificate nearing expiry fans out over
  the monitor's alert channels but never opens an incident; incidents stay strictly downtime.
  Expiry warnings de-duplicate through `ssl_notified_days` (the smallest threshold already warned,
  reset when a renewal pushes the expiry later) — one warning per threshold per certificate.
  Modeling expiry as an incident was rejected because it would pollute uptime percentages and the
  incident timeline with events that are not outages.

- **Two independent layers stop double-dispatch.** The dispatcher claims each due monitor with a
  conditional `UPDATE monitors SET next_check_at = ... WHERE id = ? AND next_check_at <= ?` and
  only dispatches when exactly one row is affected; the `RunHttpCheck` job additionally holds a
  `WithoutOverlapping(monitor_id)` lock with `dontRelease()`. Either layer alone would mostly
  work; both together make concurrent checks of one monitor impossible even across retries or a
  multi-server cron.

- **Alert de-duplication is structural, not a flag.** Incident alerts fire only inside the
  open/close transitions of `Monitor::applyCheckResult`. A failed check against an already-open
  incident records a raw check row and nothing else. There is no "already alerted" flag to keep in
  sync because there is only one code path that can emit an incident alert.

## Benchmark

Rollups are the app's heaviest batch job: the hourly command scans a monitor's raw `checks` rows
(the write-hot table) and aggregates them in PHP. The numbers below come from
`benchmarks/rollup_benchmark.php`, which seeds a throwaway SQLite database and times each rollup
command; re-run it locally with `php benchmarks/rollup_benchmark.php [monitors] [days] [per_hour]`.

Measured on a 12th Gen Intel Core i5-1235U (12 threads), 31 GB RAM, PHP 8.2.29, SQLite 3.37.2
(`journal_mode=MEMORY`, `synchronous=OFF`), single run each, machine otherwise loaded:

| Raw `checks` rows | Monitors × days | `rollup hour` | `rollup day` | Peak memory |
|---|---|---:|---:|---:|
| 216,000 | 50 × 3 | ~7.2 s | ~0.15 s | 126 MB |
| 504,000 | 50 × 7 | ~16.7 s | ~0.37 s | 152 MB |
| 1,008,000 | 100 × 7 | ~44.5 s | ~0.84 s | 152 MB |

The hourly rollup cost tracks raw row volume, which is exactly why raw checks are pruned after 7
days by default and why every dashboard, status page, and uptime figure reads from rollups rather
than scanning raw rows. The daily rollup works from the compact hourly rows and stays well under a
second even at a million raw checks. Runs above the default 128 MB `memory_limit` need the raised
limit the script sets for itself.

## License

License: MIT
