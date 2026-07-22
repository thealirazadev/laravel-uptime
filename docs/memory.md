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

- 2026-07-22 — Repo housekeeping: added root `LICENSE` (MIT, matching `composer.json`) and
  `.github/workflows/ci.yml`. CI runs on push and pull_request to `main`: PHP 8.2 via
  `shivammathur/setup-php@v2`, cached composer downloads, `composer install --prefer-dist
  --no-progress`, `cp .env.example .env`, `php artisan key:generate`, then the two gate commands
  from docs/testing.md in their documented order — `./vendor/bin/pint --test` and
  `php artisan test`. Verified first against a clean clone locally.

- 2026-07-23 — Added README screenshots. `database/seeders/DemoSeeder.php` seeds synthetic
  `example.com` data (six monitors in up/down/paused states, an SSL-warning monitor, 24 h hourly
  and 30 d daily rollups for the charts, a public "Acme Cloud" group, one open and two resolved
  incidents with timelines). `scripts/capture-screenshots.mjs` drives Playwright (not a repo
  dependency) to shoot the dashboard, monitor detail, public status page, and incident timeline
  into `docs/images/` at 1280-wide; all four PNGs are 60-89 KB. Referenced near the top of the
  README with descriptive alt text plus a reproduction section. Genuine captures of the running
  app — no application code changed. Sandbox note: `php artisan serve` still fails (libtidy), and
  `php -S ... public/index.php` routes every request through the front controller so static CSS
  302s and renders unstyled; captured via `php -S -t public <router>` where the router returns
  false for existing files so assets serve. README documents the normal `php artisan serve` path.
  pint clean, 137 tests pass, CI green.

- 2026-07-23 — Fixed a real edge case: a check's redirect chain could outlast its overlap lock.
  `RunHttpCheck` follows up to 5 redirects, each with its own timeout (<= 30 s), so a worst-case run
  is (1 + max_redirects) * max_timeout = 6 * 30 = 180 s, well past the old `expireAfter(60)`. Once
  the lock expired, the next scheduler tick's job could acquire the now-free lock and run a second
  check of the same monitor concurrently (the `next_check_at` claim advances at dispatch time, so it
  does not prevent this). Chose to ALIGN THE LOCK TTL WITH THE REAL MAX RUNTIME rather than bound
  total request time: it is the smaller, more obviously correct change, keeps the per-monitor timeout
  semantics untouched, and preserves full redirect support. Holding the lock for the worst case is
  also the desired behavior — while one check is genuinely still in flight against a slow target you
  do NOT want a second concurrent check; `dontRelease()` releases the lock the instant a normal (fast)
  check finishes, so the long TTL only ever matters as the stale-lock ceiling for a crashed worker.
  Centralized the two bounds as `Monitor::MAX_TIMEOUT_SECONDS` (30) and `Monitor::MAX_REDIRECTS` (5),
  mirroring the existing `Monitor::INTERVALS` pattern; both form requests now validate the timeout
  against the constant so the invariant (actual runtime <= lock TTL) cannot drift. `RunHttpCheck::
  lockSeconds()` returns `(1 + MAX_REDIRECTS) * MAX_TIMEOUT_SECONDS + 15` (= 195 s; the 15 s buffer
  covers the body scan and check/incident writes). Test asserts the overlap middleware's
  `expiresAfter` is >= the worst-case runtime. RunSslCheck is unaffected: its TLS read uses a fixed
  10 s connect timeout and follows no redirects, so 60 s remains ample.

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
- 2026-07-22 — CI runs only the two automated gates from docs/testing.md. Deliberately out of
  scope: the MySQL 8 sanity pass and the rest of docs/testing.md "Manual QA only" (real SMTP/Slack
  delivery, long-running scheduler/worker observation, a11y review) — they need real services or
  human judgment. No migration step either: `phpunit.xml` pins the suite to in-memory SQLite and
  `RefreshDatabase` builds the schema, so no database file is created in CI.
- 2026-07-22 — Security upgrade pass. Owner explicitly approved breaking majors, which unblocked
  the Laravel 11 → 12 move that the 2026-07-18 entry had deferred. Cleared all 5 open Dependabot
  alerts: guzzlehttp/guzzle 7.15.0 → 7.15.1 (3 medium: host-only cookie scope, unbounded response
  cookies, URI fragments in redirect Referer headers) and laravel/framework 11.55.0 → 12.64.0
  (1 high CRLF injection in the default email rule, fixed only in 12.60.0+; 1 medium temporary
  signed URL path confusion, fixed only in 12.61.1+ — neither has an 11.x patch, so the major was
  the only route). The 11 → 12 upgrade needed no application code and no config changes: the app
  uses none of the breaking surfaces (no `HasUuids`, no `Concurrency::run`, no `image` validation
  rule, no `Schema::getTables()`, no `mergeIfMissing`, and `config/filesystems.php` already
  defines the `local` disk explicitly with the 12.x root and `serve`). Carbon was already on 3.x.
  Laravel 12 still requires PHP ^8.2, so the CI matrix stays on 8.2. Dev tooling was already at
  Laravel-12-compatible versions and was left untouched (pest 3.8.7 allows ^12.9.2, collision
  8.9.5, phpunit 11.5.56 — the upgrade guide asks for exactly these lines). 137 tests still pass,
  pint clean, `composer audit` reports zero advisories. laravel/tinker 3.0.2 exists but is a
  non-security major with no advisory against 2.11.1, so it stays pinned.
