# laravel-uptime

A self-hosted uptime, SSL-expiry, and site monitor built with Laravel. A freelancer or small agency
adds the sites they manage; the app runs scheduled HTTP and certificate checks from queued jobs,
records response times, opens and closes incidents with confirmation thresholds, and sends alerts
over mail, Slack incoming webhooks, or a signed generic webhook. Each monitor group gets a public
status page; operators manage everything from a plain Blade dashboard.

Status: v1 implemented (monitors, overlap-safe queued checks, incident lifecycle, alerts, SSL
expiry, rollups/retention, public status pages).

## Planned stack

- PHP 8.2+ / Laravel 11.x
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

## License

License: MIT
