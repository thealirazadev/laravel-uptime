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

- 2026-07-18 — Phase 2 complete and verified. Alert channels (encrypted config, masked
  secrets, CRUD, per-monitor routing), `AlertPayload` + `AlertSender` (mail/slack/webhook),
  `SendAlert` job (tries 3, backoff 60/300) with structural dedup (alerts dispatch only inside
  the incident open/close transitions), send-test action, SSL expiry (`Ssl` reader, threshold
  state machine, `RunSslCheck` + `uptime:dispatch-ssl` daily), rollups (`uptime:rollup hour|day`)
  and pruning (`uptime:prune`), all scheduled (rollup before prune). Full suite green: 102 tests
  / 286 assertions; `pint --test` clean. End-to-end webhook-failure run confirmed the webhook URL
  and secret never reach the logs.

- 2026-07-18 — Phase 3 complete. Monitor group CRUD with kebab slugs (auto-derived, unique) and
  group assignment on the monitor form; public status page (HTML) and JSON twin sharing one cached
  (~60 s) data builder, identical 404 for unknown/non-public slugs, no URL/operator/error leakage;
  `Support/Chart` inline-SVG response-time and uptime charts on the monitor detail (rollup-backed,
  accessible, honest empty state); login (5/min) and status (60/min) IP throttling with the JSON
  error envelope on 429; custom 404/429/500 pages and a status-JSON error envelope. Full suite
  green: 135 tests / 497 assertions; `pint --test` clean. Status/error pages verified over HTTP for
  landmarks, lang, viewport, and zero URL leakage.

- 2026-07-18 — MySQL 8 sanity pass done: migrations apply cleanly and the full suite (135 tests /
  497 assertions) passes against MySQL 8.4 as well as SQLite. Portability holds (PHP-side rollup
  bucketing, standard-SQL ordering, upserts, and the conditional-UPDATE claim behave identically).

## In progress

- None. v1 implementation complete across all three phases; remaining items are the human-only
  production steps in docs/launch-checklist.md.

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
  active). Group selection on the monitor form is deferred to Phase 3.
- 2026-07-18 — `AlertPayload` carries primitive arrays (not Eloquent models) so a queued
  `SendAlert` serializes plain data and every sender reads the same facts. Sender resolution is a
  match in the job (no extra factory), honoring the "three senders, no more seams" rule.
- 2026-07-18 — `SendAlert` catches the sender's exception and re-throws a sanitized one
  (no URL/secret; keeps HTTP status). The queue worker logs the thrown exception on every failed
  attempt, so the original URL-bearing exception must never escape; `alert_failed` is recorded in
  `failed()` after the final retry. Verified logs carry no channel URL or secret.
- 2026-07-18 — Rollup buckets are grouped in PHP via Carbon `startOfHour`/`startOfDay` (no
  DATE()/strftime()), upserted on (monitor_id, period, period_start) for idempotency. Daily avg
  response time weights each hour's avg by its `checks_total` (per-hour responsive counts are not
  stored, keeping the documented CheckRollup schema) — an approximation for the chart; totals and
  uptime% stay exact. `uptime:prune` also drops daily rollups past `daily_retention_days` to fully
  bound growth.
- 2026-07-18 — Alert-channel type is immutable on edit (config shape can't drift); Slack/webhook
  URLs and the webhook secret are never pre-filled — a blank field keeps the stored value.
- 2026-07-18 — Blade gotcha logged: an inline `@endunless` (or any `@end*`) directly preceded by
  a word character (e.g. `disabled@endunless`) is not compiled (its `\B@` regex needs a non-word
  boundary), leaving the `if` unclosed. Use a `{{ ternary }}` for inline conditionals instead.
- 2026-07-18 — Status page HTML and JSON share one cached data array so they never diverge; caching
  keyed by group id, ~60 s. Uptime% is derived at read time from rollups (hourly for 24 h, daily
  for 7/30 d), never stored; null when a window has no rollup data. Monitor URLs are never emitted
  on the public surfaces.
- 2026-07-18 — Rate limiting via named limiters in AppServiceProvider: `login` 5/min per IP,
  `status` 60/min per IP shared by HTML+JSON (JSON 429 returns the `rate_limited` envelope, HTML
  returns the 429 view). Gotcha logged: a global `render()` callback runs BEFORE the framework's
  `HttpResponseException` branch, so it must return null for `HttpResponseException` (which wraps
  the throttle's own 429 response) or it clobbers throttle/redirect responses into 500s.
- 2026-07-18 — Charts are pure inline SVG from `Support/Chart`, interpolating only computed
  numbers, rendered with `{!! !!}` (the one sanctioned unescaped output); failures are marked by
  position/marker, not colour alone; brand-new monitors render a "Not enough data yet" state.
