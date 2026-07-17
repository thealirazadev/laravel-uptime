# Project Memory — laravel-uptime

Running log of what is done, in progress, and decided. Update after every meaningful chunk of
work; log every non-obvious decision with its reason. Keep entries short and dated.

## Completed

- 2026-07-18 — Planning documentation created (README, PRD, architecture, rules, phases, design,
  testing, api-contracts, launch-checklist, memory, .env.example). Awaiting owner review; no
  code yet.

## In progress

- None. Implementation has not started.

## Decisions log

- 2026-07-18 — Stack fixed: Laravel 11.x / PHP 8.2+, Blade + session auth, database
  queue/cache/session drivers (no Redis), SQLite dev / MySQL 8 prod, Pest + Pint. No
  Sail/Docker requirement, deviating from laravel-shortlink: the brief mandates zero
  infrastructure beyond PHP + one database.
- 2026-07-18 — Double-dispatch safety = conditional UPDATE claim on `monitors.next_check_at`;
  overlap safety = `WithoutOverlapping(monitor_id)` with database cache locks. Two independent
  layers by design.
- 2026-07-18 — Alert de-duplication is structural: alerts dispatch only inside the open/close
  transitions of `Monitor::applyCheckResult`, so there is no dedup flag to keep in sync.
- 2026-07-18 — SSL warnings do not open incidents; they dedupe via `ssl_notified_days`
  (smallest threshold already alerted, reset on renewal). Incidents remain downtime-only.
- 2026-07-18 — Retention defaults: raw 7 d, hourly 90 d, daily 365 d; rollups upsert on
  `(monitor_id, period, period_start)` and always run before pruning.
- 2026-07-18 — Charts are server-rendered inline SVG; no JS chart library, no npm build in v1.
- 2026-07-18 — Slack/webhook URLs and secrets are per-channel DB data under an
  `encrypted:array` cast, not env vars; only SMTP credentials live in `.env`.
