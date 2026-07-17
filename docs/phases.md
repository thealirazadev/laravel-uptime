# Phases — laravel-uptime

Phase N+1 does not start until the owner approves phase N. Phases are ordered
smallest-useful-shippable first; each ends green (app runs, tests pass, logs clean). One commit
per feature/task, Conventional Commits, in the listed order.

The three senior differentiators are hard requirements placed here, not stretch goals:
overlap-safe queued checks and confirmation-threshold incidents in Phase 1; alert
de-duplication and retention/rollups in Phase 2.

---

## Phase 1 — Foundation, monitor CRUD, and the overlap-safe check engine

Goal: an operator can log in, create monitors, and watch the engine flip them up/down with
confirmation thresholds and an incident timeline — no alerts yet, but the concurrency and
state-machine core is finished and tested.

### Definition of done

- App boots on host PHP with SQLite (`.env` from `.env.example`, `APP_KEY` generated); queue and
  cache run on the database driver; migrations for framework tables plus `monitor_groups`,
  `monitors`, `checks`, `incidents`, `incident_events` applied.
- Models with relationships, `$fillable`, `$casts`, factories.
- Session login/logout; `uptime:user` command creates operators; all dashboard routes behind
  `auth`; no registration route.
- Monitor CRUD (index, create, edit, delete, pause via `is_active`) with full server-side
  validation per `docs/rules.md`.
- `uptime:dispatch-checks` runs every minute via the scheduler, claims due monitors with the
  conditional `next_check_at` UPDATE, dispatches `RunHttpCheck` only on a one-row claim, and is
  registered `onOneServer()`.
- `RunHttpCheck` carries `WithoutOverlapping(monitor_id)->dontRelease()`, performs the request
  (timeout, expected status, keyword), writes exactly one `checks` row per run, and never throws
  on a failing target.
- `Monitor::applyCheckResult` implements the counters, `first_failed_at`, and the
  threshold-gated transitions; down-transition opens an incident (with `opened` event,
  `started_at` = first failure of streak); up-transition closes it (`closed` event);
  `unknown → up` is silent.
- Dashboard overview lists monitors with status badges and last-check info; monitor detail shows
  recent checks and its incidents; incidents index/show render timelines.
- Structured log events from `docs/rules.md` emitted for check/monitor/incident lifecycles.
- Pint clean; Pest feature + unit tests for all of the above pass.

### Manual test checklist

- `php artisan uptime:user` → log in with those credentials; wrong password → safe error, no
  user enumeration.
- Visit `/dashboard` logged out → redirected to `/login`.
- Create a monitor pointing at a URL you control → appears as `unknown`; invalid URL or interval
  → field errors, nothing saved.
- Run `php artisan schedule:work` and `php artisan queue:work` → within the interval the monitor
  goes `up`; a `checks` row exists with a response time.
- Point a monitor (threshold 2) at a dead port → first failure: still `up`/`unknown`, no
  incident; second: status `down`, one incident with `opened` event and correct `started_at`.
- Bring the target back → after two successes the incident closes with a `closed` event.
- Pause the monitor → no new checks appear on the next ticks.
- Run two `queue:work` processes and a busy monitor → still exactly one check row per interval.

### Commits

1. `chore: scaffold laravel app with sqlite and database queue`
2. `chore: add env example and uptime config`
3. `feat: add monitor check and incident migrations`
4. `feat: add monitor check and incident models with factories`
5. `feat: add session login and uptime user command`
6. `feat: add monitor crud screens with validation`
7. `feat: add due check dispatcher with atomic claim`
8. `feat: add queued http check job with overlap lock`
9. `feat: apply confirmation thresholds in monitor state machine`
10. `feat: open and close incidents with event timeline`
11. `feat: add dashboard overview and incident views`
12. `test: cover dispatcher claim state machine and incident lifecycle`

### Phase 1 verification

- [ ] App runs: `php artisan serve` (or `php -S`) + `queue:work` + `schedule:work`; logs clean.
- [ ] `php artisan test` green; `./vendor/bin/pint --test` clean; no console/log warnings during
      a manual run.
- [ ] Unhappy paths: dead host, timeout, wrong status, missing keyword each produce a failed
      check with the right `error` reason and never a job exception; duplicate form submission
      (double-click create) yields one monitor; refresh mid-edit loses nothing committed.
- [ ] Concurrency: claim test proves a second dispatcher tick cannot re-dispatch a claimed
      monitor; overlap test proves a second identical job is dropped.
- [ ] Empty states: zero monitors and zero incidents render guidance, not blank tables.
- [ ] Long inputs: 2048-char URL accepted, longer rejected with a field error; long keyword and
      long error reasons stored truncated without a 500.

---

## Phase 2 — Alerts, SSL expiry, and retention/rollups

Goal: incidents notify people exactly once per edge, certificates warn before they expire, and
the checks table stops growing forever. All remaining differentiators land here.

### Definition of done

- `alert_channels` + pivot migrations; channel CRUD with per-type validated config stored via
  `encrypted:array`; secrets masked on edit; enable/disable toggle.
- Per-monitor channel routing (checkbox list on the monitor form).
- `SendAlert` job (tries 3, backoff 60/300) delivering via `MailSender`, `SlackSender`,
  `WebhookSender` (payloads and signature per `docs/api-contracts.md`); outcome recorded as
  `alert_sent`/`alert_failed` incident events plus structured logs; one channel's failure never
  blocks another.
- De-duplication is structural: alerts dispatch only inside the open and close transitions of
  `applyCheckResult`; an open incident never re-alerts; recovery alerts fire on close.
- "Send test" action on a channel delivers a test payload and flashes the outcome.
- `RunSslCheck` + `uptime:dispatch-ssl` (daily): reads cert expiry, stores it, warns through the
  monitor's channels per the threshold algorithm in `docs/architecture.md` (once per threshold
  per cert, reset on renewal, expiry warns once); SSL connection failure logs and never alerts.
- `check_rollups` migration; `uptime:rollup hour|day` upserts idempotent aggregates;
  `uptime:prune` removes raw checks and hourly rollups past retention; scheduled hourly/daily
  with rollup strictly before prune; config windows honored.
- Monitor detail shows SSL expiry state and days left.
- Pint clean; tests cover dedup, ssl thresholds (with `Carbon::setTestNow`), rollup math,
  idempotent re-runs, and pruning windows.

### Manual test checklist

- Create a mail channel (Mailpit/log mailer) and a Slack channel with a real webhook; "send
  test" on each → message arrives; a bad webhook URL → friendly failure flash, detailed log.
- Attach both channels to a threshold-2 monitor; kill the target → exactly one down alert per
  channel at confirmation, none on later failing checks; restore → exactly one recovery alert
  per channel.
- Detach a channel, disable another → next incident alerts neither.
- Point a monitor at a site with a soon-expiring cert (or fake `Ssl`) → warning fires once for
  the crossed threshold; rerun the daily check → silence.
- Seed a few days of checks, run `uptime:rollup hour` then `day` twice → rollup rows exist once,
  totals match; run `uptime:prune` → raw rows older than the window gone, recent ones intact.

### Commits

1. `feat: add alert channel and pivot migrations with models`
2. `feat: add alert channel crud with encrypted config`
3. `feat: add per monitor channel routing`
4. `feat: add queued alert job with mail slack and webhook senders`
5. `feat: dispatch alerts only on incident open and close`
6. `feat: add send test action for channels`
7. `feat: add ssl expiry check with threshold warnings`
8. `feat: add check rollup migration and rollup command`
9. `feat: prune raw checks and stale rollups on schedule`
10. `test: cover alert dedup ssl thresholds rollups and pruning`

### Phase 2 verification

- [ ] App + workers + scheduler run clean; `php artisan test` green; Pint clean.
- [ ] Unhappy paths: SMTP down → alert retried then `alert_failed` event + log, incident state
      untouched; webhook 500 → same; malformed Slack URL rejected at validation; SSL port closed
      → `ssl.check_failed` logged, no alert, stale values kept; rollup re-run → no duplicates or
      double counts.
- [ ] Dedup proof: flapping sequence (fail, ok, fail, ok...) below threshold → zero alerts; 50
      consecutive failures → exactly one alert per channel.
- [ ] Empty states: no channels → monitor form says so and links to create one; channel with no
      monitors renders fine.
- [ ] Long inputs: long channel name and long webhook URL validated within limits; no secret or
      webhook URL ever appears in logs or error messages (grep the log after the run).

---

## Phase 3 — Public status pages, charts, and hardening

Goal: shareable per-group status pages (HTML + JSON), rollup-backed charts on the dashboard, and
production polish.

### Definition of done

- Monitor group CRUD (name, unique kebab slug, `is_public`); group assignment on the monitor
  form; deleting a group detaches (nulls) its monitors.
- `GET /status/{slug}`: public Blade page — group name, overall state, per-monitor status badge,
  uptime 24 h / 7 d / 30 d, average response time, recent incidents (last 14 days, names and
  times only). Non-public or unknown slug → 404. Response cached ~60 s.
- `GET /status/{slug}/json`: same data per the contract in `docs/api-contracts.md`, same 404
  rules, JSON error envelope.
- Monitor detail charts from rollups via `Support/Chart` inline SVG: response time (24 h hourly,
  30 d daily) and uptime bar; accessible per `docs/design.md`.
- Rate limiting: login 5/min per IP; status HTML + JSON 60/min per IP; 429 behavior per
  `docs/api-contracts.md`.
- Custom 404 and 500 pages; all empty states reviewed; README Install/Run/Test finalized and
  `docs/testing.md` commands verified.
- Pint clean; tests cover status page data correctness, visibility rules, caching header
  behavior, chart data selection, and throttling.

### Manual test checklist

- Create group "Client A", assign two monitors → `/status/client-a` (logged out) shows both with
  plausible uptime numbers matching the dashboard; monitor URLs appear nowhere in the HTML
  source.
- Toggle `is_public` off → 404 page. Unknown slug → 404 page. `/status/client-a/json` → contract
  shape; unknown slug → JSON error envelope.
- Take a monitor down (confirmed) → status page shows it down and lists the incident within a
  cache window.
- Monitor detail: charts render and match rollup numbers; a brand-new monitor shows an honest
  "not enough data yet" state.
- Hammer `/status/client-a` past the limit → 429; login brute force → 429 after 5 tries.
- Force a 500 (temporarily) with `APP_DEBUG=false` → friendly page, no trace.

### Commits

1. `feat: add monitor group crud with slugs`
2. `feat: add public status page`
3. `feat: add status page json endpoint with caching`
4. `feat: add rollup backed charts to monitor detail`
5. `feat: throttle login and status routes`
6. `feat: add custom error pages and empty states`
7. `docs: finalize readme and testing commands`
8. `test: cover status pages charts and throttling`

### Phase 3 verification

- [ ] Full stack (serve + queue + schedule) runs clean end to end; `php artisan test` green;
      Pint clean; log shows only expected structured events during a manual pass.
- [ ] Unhappy paths: unknown slug (HTML and JSON), private group, throttle exceedance, 500 path,
      duplicate slug submission → validation error; group delete leaves monitors intact and off
      the page.
- [ ] Empty states: group with zero monitors renders an honest empty status page; new monitor
      charts degrade gracefully.
- [ ] Long inputs: long group names render without breaking layout; slug length capped.
- [ ] Accessibility: status page and error pages pass the `docs/design.md` baseline (landmarks,
      contrast, keyboard, status not conveyed by color alone).
- [ ] MySQL sanity pass: migrations and the full test-relevant flows run against MySQL 8 once
      before sign-off (dev default remains SQLite).

## Backlog

_(empty — move out-of-scope ideas here with a one-line rationale)_
