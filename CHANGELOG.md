# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project aims to follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

There are no tagged releases yet; the project ships from `main`. The entry below describes the v1
functionality that currently exists.

## [Unreleased]

### Added

- **Operator access.** Session-based login/logout with no public registration; operators are
  created from the CLI with `php artisan uptime:user`. All dashboard routes are behind `auth`.
- **Monitor management.** CRUD for HTTP/HTTPS monitors with server-side validation (URL scheme,
  interval set, timeout, expected status, expected keyword, confirmation threshold) and a pause
  toggle.
- **Overlap-safe check engine.** `uptime:dispatch-checks` claims due monitors with a conditional
  `next_check_at` update and dispatches `RunHttpCheck` only on a one-row claim; the job holds a
  per-monitor `WithoutOverlapping` lock sized to its worst-case redirect runtime. A failing target
  is recorded as a failed check, never an unhandled job exception.
- **Incident lifecycle.** `Monitor::applyCheckResult` applies confirmation thresholds, opens an
  incident on confirmed downtime (with the streak's first-failure `started_at`), and closes it on
  confirmed recovery, recording a timeline of events.
- **Alerts.** Mail, Slack incoming webhook, and signed generic webhook channels with per-type
  encrypted config and masked secrets; per-monitor routing; a "send test" action. Delivery runs in
  the `SendAlert` job with retries and backoff. De-duplication is structural — alerts fire only on
  the incident open and close transitions — and one channel's failure never blocks another.
- **SSL expiry monitoring.** A daily certificate check stores the expiry and warns through the
  monitor's channels once per crossed threshold, resetting on renewal. TLS read failures are logged
  without alerting and never open an incident.
- **Rollups and retention.** `uptime:rollup hour|day` upserts idempotent aggregates and
  `uptime:prune` removes raw checks and stale rollups past their retention windows, scheduled with
  rollup strictly before prune.
- **Public status pages.** Per-group HTML status page and a JSON twin sharing one ~60-second cached
  data builder, served only for public groups, with identical 404s for unknown and non-public
  slugs and no leakage of monitor URLs, operator identity, or raw error text.
- **Charts.** Server-rendered inline SVG response-time and uptime charts on the monitor detail,
  built from rollups with an honest "not enough data yet" empty state; no JavaScript charting
  library or build step.
- **Hardening.** Per-IP rate limiting on login and the status pages with a consistent JSON error
  envelope on 429; custom 404/429/500 pages; structured logging throughout.
- **Project support files.** CI running the Pint and Pest gates, a Dependabot config, a security
  policy, a demo seeder and screenshot capture script, and this contributing/conduct/changelog set.
