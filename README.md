# laravel-uptime

A self-hosted uptime, SSL-expiry, and site monitor built with Laravel. A freelancer or small agency
adds the sites they manage; the app runs scheduled HTTP and certificate checks from queued jobs,
records response times, opens and closes incidents with confirmation thresholds, and sends alerts
over mail, Slack incoming webhooks, or a signed generic webhook. Each monitor group gets a public
status page; operators manage everything from a plain Blade dashboard.

Status: planning — docs under review

## Planned stack

- PHP 8.2+ / Laravel 11.x
- Blade + minimal server-rendered SVG charts (no SPA, no JS framework)
- SQLite for local development, MySQL 8.x in production
- Laravel scheduler + database queue driver (no Redis dependency)
- Pest (feature + unit tests) on PHPUnit, Laravel Pint (PSR-12 formatting)

See `docs/` for the PRD, architecture, API contracts, phases, and engineering rules.

## Install

TBD until implementation starts.

## Run

TBD until implementation starts.

## Test

TBD until implementation starts.

## License

License: MIT
