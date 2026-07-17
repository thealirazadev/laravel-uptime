# Project Memory — laravel-uptime

Running log of what is done, in progress, and decided. Update after every meaningful chunk of
work; log every non-obvious decision with its reason. Keep entries short and dated.

## Completed

- 2026-07-18 — Planning documentation created (README, PRD, architecture, rules, phases, design,
  testing, api-contracts, launch-checklist, memory, .env.example). Awaiting owner review; no
  code yet.
- 2026-07-18 — Phase 1 complete and verified. Scaffolded Laravel 11.55.0 (SQLite dev, database
  queue/cache/session), `config/uptime.php`, migrations (monitor_groups, monitors, checks,
  incidents, incident_events), models + factories, session auth + `uptime:user` command, monitor
  CRUD with Form Request validation, `CheckOutcome` value object, `Monitor::applyCheckResult`
  threshold state machine, incident open/close with timeline, `RunHttpCheck` job
  (WithoutOverlapping + dontRelease), `uptime:dispatch-checks` atomic-claim dispatcher (scheduled
  every minute, onOneServer + withoutOverlapping), dashboard overview, incident list/detail. Full
  suite green: 50 tests / 144 assertions; `pint --test` clean. End-to-end smoke passed against a
  live local target over the database queue (up path, and 2-cycle down path opening one incident
  with started_at at the first failure). Logs show only structured events, no traces.

## In progress

- Phase 2 next: alert channels, SSL expiry, retention/rollups.

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
- 2026-07-18 — Dependency versions pinned to match sibling `laravel-shortlink`
  (laravel/framework 11.55.0, pint 1.29.3, pest 3.8.7, etc.). Dropped `laravel/sanctum`
  (session auth only) and `laravel/sail` (no Docker requirement) per architecture; added Pest.
  Removed npm/vite/tailwind scaffolding — design mandates one hand-written stylesheet, no build.
- 2026-07-18 — `composer audit` flags 3 advisories (medium/high) on the whole Laravel 11.x line,
  fixed only in 12.60.0+/13.x. Kept the owner-approved pinned Laravel 11.x rather than a major
  upgrade; FLAG FOR OWNER before production (path-confusion in signed URLs, CRLF in the default
  email rule — neither on this app's current surface). Revisit if a patched 11.x ships.
- 2026-07-18 — `php artisan serve` is unusable in this dev environment: it spawns the built-in
  server with a Lightning PHP binary that fails to load `libtidy.so.5deb1`. Not an app defect —
  the PATH `php` runs tests/tinker/queue:work fine. For manual runs use
  `php -S 127.0.0.1:8000 -t public public/index.php`.
- 2026-07-18 — Incident-index "open first" ordering uses `orderByRaw('closed_at is null desc')`;
  standard SQL, verified identical on SQLite (no DATE()/strftime()), so it stays portable to MySQL.
- 2026-07-18 — Adopted small/granular commits from mid-Phase-1 per owner instruction (one artifact
  per commit: migration, model, job, route, view group, test). Commits 1-4 predate the switch.
- 2026-07-18 — New monitors set `next_check_at = now()` in the controller so the next scheduler
  tick checks them immediately; `is_active` toggle lives on the edit form only (create defaults
  active). Group/channel selection on the monitor form is deferred to Phases 2-3.
