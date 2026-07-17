# Architecture — laravel-uptime

## App flow

```
Scheduler (every minute, cron: schedule:run)
        │
        ▼
uptime:dispatch-checks ──▶ claim due monitors (atomic per-row UPDATE on next_check_at)
        │                   one RunHttpCheck job per claimed monitor → database queue
        ▼
queue:work (1..n workers, horizontally scalable)
        │
        ▼
RunHttpCheck [WithoutOverlapping(monitor_id)]
        ├─ HTTP request (timeout, expected status, optional keyword)
        ├─ persist raw Check row (ok, http_status, response_time_ms, error)
        └─ Monitor::applyCheckResult()  — the state machine
                ├─ ok:   successes++, failures = 0
                │        └─ if down AND successes >= threshold → status = up,
                │           close Incident, dispatch SendAlert(recovery) per channel
                └─ fail: failures++, successes = 0
                         └─ if not down AND failures >= threshold → status = down,
                            open Incident (started_at = first failure of streak),
                            dispatch SendAlert(down) per channel
        │
        ▼
SendAlert (queued, per channel, tries + backoff)
        ├─ mail    → Laravel Mail (SMTP from .env)
        ├─ slack   → HTTP POST to incoming-webhook URL (stored encrypted per channel)
        └─ webhook → HTTP POST JSON + optional HMAC signature header
        └─ outcome recorded as incident event (alert_sent / alert_failed)

Scheduler (daily 03:00) ──▶ uptime:dispatch-ssl ──▶ RunSslCheck per active https monitor
        └─ read cert expiry; warn at 30/14/7/0 days, once per threshold per cert

Scheduler (hourly :05 / daily 00:15) ──▶ uptime:rollup hour|day ──▶ upsert CheckRollup rows
        └─ daily also prunes raw checks and expired hourly rollups (rollup before prune)

Operator (session auth) ──▶ Blade dashboard: monitors, channels, groups, incidents
Visitor (no auth)       ──▶ GET /status/{slug} (HTML) and /status/{slug}/json (cached, throttled)
```

## The three concurrency/correctness mechanisms (senior differentiators)

These are core Phase 1–2 requirements, not options.

### 1. Double-dispatch and overlap protection

Two independent layers, both required:

- **Atomic claim in the dispatcher.** `uptime:dispatch-checks` selects candidate ids
  (`is_active = 1 AND next_check_at <= now`), then for each id executes a conditional update:
  `UPDATE monitors SET next_check_at = now + interval_seconds WHERE id = ? AND next_check_at <= ?`
  (bound to the `now` captured at selection). Only when the update reports one affected row is
  `RunHttpCheck` dispatched. Two scheduler processes racing on the same row means one claim wins
  and one no-ops — no double dispatch without locks or transactions across rows. The scheduled
  task additionally runs `onOneServer()` (cache locks on the database cache store) as a
  belt-and-braces layer for multi-server cron.
- **Overlap lock on the job.** `RunHttpCheck` uses the `WithoutOverlapping($monitorId)` job
  middleware (atomic cache locks via the `cache_locks` table) with an expiry safely above the
  request timeout, and `dontRelease()` — a duplicate job is dropped, not retried. Two workers can
  never execute checks for the same monitor concurrently, even after a retry or a stuck job.

### 2. Flap suppression and alert de-duplication

The state machine lives in one place, `Monitor::applyCheckResult(CheckOutcome $outcome)`:

- Counters `consecutive_failures` / `consecutive_successes` are mutually resetting.
  `first_failed_at` is set when failures go 0 → 1 and cleared on any success; it becomes the
  incident's `started_at` so the incident reflects when trouble began, not when it was confirmed.
- Transitions happen only at the threshold: `up|unknown → down` at N consecutive failures,
  `down → up` at N consecutive successes. `unknown → up` (first-ever confirmation) is silent — no
  incident, no alert.
- Alerts fire only inside a transition: one `incident.opened` alert per attached enabled channel
  when the incident opens, one `incident.closed` alert per channel when it closes. A failed check
  against an already-open incident writes a Check row and nothing else. There is no other code
  path that dispatches incident alerts, which is what makes de-duplication structural rather than
  a flag to keep in sync.
- Because the mutation runs inside the per-monitor overlap lock, counters never interleave.

### 3. Retention and rollups

Raw `checks` rows are the write-hot table and are never queried over long ranges:

- Hourly (`uptime:rollup hour`): aggregate each monitor's raw checks for completed hours into
  `check_rollups` (`period = hour`) via upsert on `(monitor_id, period, period_start)` —
  idempotent, safe to re-run.
- Daily (`uptime:rollup day`): aggregate hourly rows into daily rows, then prune raw checks older
  than `UPTIME_RAW_RETENTION_DAYS` (default 7) and hourly rollups older than
  `UPTIME_HOURLY_RETENTION_DAYS` (default 90). Daily rollups keep
  `UPTIME_DAILY_RETENTION_DAYS` (default 365). Rollup always runs before prune in the same
  scheduled sequence, so nothing is deleted before it is aggregated.
- Uptime % is derived (`1 - checks_failed / checks_total`) at read time, never stored. Charts:
  last 24 h from hourly rollups, last 30/90 d from daily rollups. The current partial hour may be
  supplemented from raw checks on the monitor detail page only.

## Check semantics

- Request: `GET` via Laravel's HTTP client, `timeout_seconds` (1–30, default 10), redirects
  followed (max 5), user agent from `UPTIME_HTTP_USER_AGENT`. TLS verification on.
- A check is **ok** when the final HTTP status equals `expected_status` (default 200) AND, if
  `expected_keyword` is set, the first 256 KB of the body contains it (case-insensitive).
- A check is **fail** on connection error, timeout, TLS failure, wrong status, or missing keyword.
  `error` stores a short reason (`timeout`, `connection_failed`, `status_mismatch:503`,
  `keyword_missing`, `tls_error`), truncated to 255 chars. `response_time_ms` is null when no
  response arrived. The job catches every throwable from the HTTP call — a check can fail, the
  job must not.

## SSL expiry semantics

- `RunSslCheck` (daily per active `https` monitor) opens a TLS connection with
  `stream_socket_client` + `capture_peer_cert` and reads `validTo` via `openssl_x509_parse`. No
  extra dependency. Connection failure: log `ssl.check_failed`, keep stale values, no alert (the
  HTTP check owns reachability).
- Warning thresholds come from `UPTIME_SSL_WARN_DAYS` (default `30,14,7`); expiry itself acts as
  an implicit final threshold 0. With `days_left = floor(now → ssl_expires_at)`, the check finds
  the smallest crossed threshold `t` (`days_left <= t`) and alerts only when
  `ssl_notified_days` is null or greater than `t`, then stores `t` in `ssl_notified_days`. One
  alert per threshold per certificate, monotonic. A renewal (new `ssl_expires_at` later than
  stored) resets `ssl_notified_days` to null. SSL warnings use the monitor's channels but do not
  open incidents.

## Folder / file tree (proposed)

```
app/
├── Console/Commands/
│   ├── CreateUser.php               # uptime:user — create a dashboard operator
│   ├── DispatchDueChecks.php        # uptime:dispatch-checks (atomic claim)
│   ├── DispatchSslChecks.php        # uptime:dispatch-ssl
│   ├── RollupChecks.php             # uptime:rollup {hour|day}
│   └── PruneChecks.php              # uptime:prune (raw + hourly retention)
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php         # showLogin, login, logout
│   │   ├── DashboardController.php          # overview
│   │   ├── MonitorController.php            # resource CRUD + detail charts
│   │   ├── AlertChannelController.php       # resource CRUD + sendTest
│   │   ├── MonitorGroupController.php       # resource CRUD
│   │   ├── IncidentController.php           # index, show
│   │   └── StatusPageController.php         # public HTML + JSON
│   └── Requests/
│       ├── LoginRequest.php
│       ├── StoreMonitorRequest.php / UpdateMonitorRequest.php
│       ├── StoreAlertChannelRequest.php / UpdateAlertChannelRequest.php
│       └── StoreMonitorGroupRequest.php / UpdateMonitorGroupRequest.php
├── Jobs/
│   ├── RunHttpCheck.php             # WithoutOverlapping(monitor_id)
│   ├── RunSslCheck.php
│   └── SendAlert.php                # tries=3, backoff [60, 300]
├── Models/
│   ├── User.php, Monitor.php, MonitorGroup.php, Check.php,
│   ├── CheckRollup.php, Incident.php, IncidentEvent.php, AlertChannel.php
└── Support/
    ├── CheckOutcome.php             # value object: ok, http_status, response_time_ms, error
    ├── Ssl.php                      # fetch + parse certificate expiry (fakeable seam)
    ├── Chart.php                    # rollup rows -> inline SVG string
    └── Alerts/
        ├── AlertSender.php          # interface: send(AlertChannel, AlertPayload)
        ├── AlertPayload.php         # value object: event, monitor, incident/ssl data
        ├── MailSender.php
        ├── SlackSender.php
        └── WebhookSender.php        # JSON POST + X-Uptime-Signature (HMAC-SHA256)

config/uptime.php                    # reads the UPTIME_* env vars
database/
├── migrations/                      # framework set (users, cache, jobs, sessions) plus:
│   ├── ..._create_monitor_groups_table.php
│   ├── ..._create_monitors_table.php
│   ├── ..._create_checks_table.php
│   ├── ..._create_check_rollups_table.php
│   ├── ..._create_incidents_table.php
│   ├── ..._create_incident_events_table.php
│   ├── ..._create_alert_channels_table.php
│   └── ..._create_alert_channel_monitor_table.php
├── factories/                       # one per model above
└── seeders/DatabaseSeeder.php       # local demo data only

resources/views/
├── layouts/app.blade.php            # authed dashboard shell
├── auth/login.blade.php
├── dashboard/index.blade.php
├── monitors/{index,create,edit,show}.blade.php
├── channels/{index,create,edit}.blade.php
├── groups/{index,create,edit}.blade.php
├── incidents/{index,show}.blade.php
├── status/show.blade.php            # public status page
├── emails/alert.blade.php           # mail alert body
└── errors/{404,500}.blade.php

routes/
├── web.php                          # auth, dashboard, public status routes
└── console.php                      # schedule definitions

tests/
├── Feature/  (Auth, MonitorCrud, DispatchClaim, HttpCheckJob, IncidentLifecycle,
│              AlertDedup, AlertChannels, SslExpiry, Rollup, Retention, StatusPage,
│              Dashboard, Throttle)
└── Unit/     (MonitorStateMachine, SslThresholds, WebhookSignature, ChartSvg)
```

## Tech stack with rationale

- **Laravel 11.x (PHP 8.2+)** — same major versions as `laravel-shortlink`; scheduler, queues,
  HTTP client, mail, atomic cache locks, and job middleware are all first-party, which is exactly
  the surface this app needs. Exact versions are pinned at install time and `composer.lock` is
  committed.
- **Database queue driver + database cache/session stores** — one moving part (the database)
  instead of two. Trade-off: lower throughput and lock granularity than Redis, irrelevant at
  hundreds of monitors (a 1-minute tick dispatches at most `monitor_count` small jobs). The
  `cache_locks` table gives real atomic locks for `WithoutOverlapping` and `onOneServer`. Redis
  would be a drop-in config change later; it is deliberately not a requirement.
- **SQLite (dev) / MySQL 8.x (prod)** — dev needs zero services (`php artisan serve` + a file);
  prod gets proper concurrent writes for multiple queue workers. Trade-off: SQLite serializes
  writes, so "two workers race" behavior is only fully exercised against MySQL — the atomic-claim
  logic is plain conditional UPDATEs that behave identically on both, and tests assert the claim
  contract, not driver internals. Anything driver-specific (no raw `DATE()`/`strftime()`) is
  banned; aggregation groups in PHP or uses portable column math.
- **No Sail/Docker requirement** — deliberate deviation from `laravel-shortlink` (which targets
  Sail): this project's brief is "no infrastructure beyond PHP + one database", and SQLite-first
  dev makes Docker pure overhead. Production is documented as plain PHP-FPM + MySQL + cron +
  `queue:work` under supervisord.
- **Blade + server-rendered SVG charts** — no chart library, no npm build for v1. `Support/Chart`
  turns rollup rows into a small inline SVG (bar/line). Trade-off: no tooltips/zoom; acceptable
  for "is it up and how slow" charts, and it keeps the page dependency-free and fast.
- **Session auth, no Sanctum** — the dashboard is server-rendered forms; Laravel's session guard
  + CSRF is the boring correct choice. There is no management API to tokenize.
- **Alert channels with zero packages** — mail via built-in Mail/SMTP; Slack incoming webhooks and
  generic webhooks are plain `Http::post` calls. No `laravel/slack-notification-channel`
  dependency.
- **Pest on PHPUnit, Laravel Pint** — same test/format tooling as the sibling Laravel project.

## Data model

### User
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, unique | login identifier |
| password | string (hashed) | |
| timestamps | | |

Created only via `php artisan uptime:user`. No registration route.

### MonitorGroup
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | shown on the status page |
| slug | string, unique | status page URL segment; kebab-case |
| is_public | bool, default true | false = status page returns 404 |
| timestamps | | |

Relationships: `hasMany(Monitor)`. Deleting a group nulls its monitors' `monitor_group_id`.

### Monitor
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| monitor_group_id | FK → monitor_groups, nullable, nullOnDelete | null = on no status page |
| name | string | display name (status page shows this, never the URL) |
| url | string(2048) | validated http/https |
| interval_seconds | unsigned int | one of 60, 300, 900, 1800, 3600 |
| timeout_seconds | unsigned tinyint, default 10 | 1–30 |
| expected_status | unsigned smallint, default 200 | exact match |
| expected_keyword | string, nullable | case-insensitive substring |
| confirmation_threshold | unsigned tinyint, default 2 | N consecutive results; 1–10 |
| is_active | bool, default true | false = dispatcher skips |
| status | enum up/down/unknown, default unknown | |
| consecutive_failures | unsigned smallint, default 0 | |
| consecutive_successes | unsigned smallint, default 0 | |
| first_failed_at | datetime, nullable | start of current failure streak |
| last_checked_at | datetime, nullable | |
| next_check_at | datetime | claim column; set to now on create |
| last_error | string, nullable | latest failure reason for display |
| ssl_expires_at | datetime, nullable | https monitors only |
| ssl_checked_at | datetime, nullable | |
| ssl_notified_days | unsigned smallint, nullable | smallest threshold already alerted |
| timestamps | | |

Relationships: `belongsTo(MonitorGroup)`, `hasMany(Check)`, `hasMany(CheckRollup)`,
`hasMany(Incident)`, `belongsToMany(AlertChannel)`.
Indexes: composite `(is_active, next_check_at)` (dispatcher scan), `monitor_group_id`.

### Check (raw, pruned)
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| monitor_id | FK → monitors, cascadeOnDelete | |
| ok | bool | |
| http_status | smallint, nullable | null when no response |
| response_time_ms | unsigned int, nullable | null when no response |
| error | string, nullable | short reason, truncated to 255 |
| checked_at | datetime | no created_at/updated_at |

Index: composite `(monitor_id, checked_at)`.

### CheckRollup
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| monitor_id | FK → monitors, cascadeOnDelete | |
| period | enum hour/day | |
| period_start | datetime | UTC bucket start |
| checks_total | unsigned int | |
| checks_failed | unsigned int | |
| avg_response_time_ms | unsigned int, nullable | over responsive checks |
| min_response_time_ms | unsigned int, nullable | |
| max_response_time_ms | unsigned int, nullable | |
| timestamps | | |

Unique: `(monitor_id, period, period_start)` — the upsert key. Uptime % is derived, not stored.

### Incident
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| monitor_id | FK → monitors, cascadeOnDelete | |
| started_at | datetime | first failure of the confirming streak |
| closed_at | datetime, nullable | null = open |
| summary | string, nullable | last_error snapshot at open |
| timestamps | | |

At most one open incident per monitor (enforced in `applyCheckResult`, which only opens on the
`* → down` transition). Relationships: `belongsTo(Monitor)`, `hasMany(IncidentEvent)`.

### IncidentEvent (timeline)
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| incident_id | FK → incidents, cascadeOnDelete | |
| type | enum opened/alert_sent/alert_failed/closed | |
| message | string | e.g. "Alert sent via Slack (Client A)" |
| created_at | datetime | no updated_at |

### AlertChannel
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| type | enum mail/slack/webhook | |
| name | string | e.g. "Agency inbox", "Client A Slack" |
| config | json, `encrypted:array` cast | mail: `{to}`; slack: `{webhook_url}`; webhook: `{url, secret?}` |
| is_enabled | bool, default true | disabled channels are skipped, not detached |
| timestamps | | |

Relationships: `belongsToMany(Monitor)` via `alert_channel_monitor`
(`alert_channel_id`, `monitor_id`, unique pair). Webhook URLs and secrets are secrets: encrypted
at rest via the cast (requires `APP_KEY`), masked in the edit form, never logged.

### Framework tables
`users`, `sessions`, `cache`, `cache_locks`, `jobs`, `failed_jobs` from the standard Laravel 11
migration set. `cache_locks` is load-bearing (overlap locks, `onOneServer`).

## Where state lives

- **Database (single source of truth)** — all domain data above, plus queue jobs, sessions,
  cache, and locks. SQLite file in dev, MySQL in prod.
- **Monitor runtime state** — on the `monitors` row itself (status, counters, `next_check_at`),
  mutated only inside `applyCheckResult` under the per-monitor overlap lock.
- **Server** — session cookie auth for operators; CSRF tokens on all forms. Status page responses
  cached ~60 s in the cache store.
- **Client** — nothing beyond the session cookie. No localStorage, no JS state.
- **Secrets** — SMTP credentials in `.env`; per-channel Slack/webhook URLs and secrets encrypted
  in `alert_channels.config`.

## External dependencies and required env vars

Runtime externals: the monitored sites themselves (outbound HTTP/TLS), an SMTP server for mail
alerts, and whatever endpoints operators configure for Slack/webhook channels. No third-party
APIs otherwise. Production host needs: PHP 8.2+, MySQL 8.x, cron (`schedule:run` every minute),
and a supervised `queue:work` process (1..n).

| Variable | Purpose |
|---|---|
| `APP_KEY` | Encryption key; also protects `alert_channels.config`. Rotating it orphans stored channel configs — flagged in the launch checklist. |
| `APP_URL` | Base URL used in alert links and status page URLs. |
| `APP_DEBUG` | Must be false outside local. |
| `DB_CONNECTION` (+ `DB_*`) | `sqlite` in dev, `mysql` in prod. |
| `QUEUE_CONNECTION` | `database`. |
| `CACHE_STORE` | `database` (locks live here). |
| `SESSION_DRIVER` | `database`. |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | SMTP for mail alerts. |
| `UPTIME_SSL_WARN_DAYS` | CSV thresholds, default `30,14,7`. |
| `UPTIME_RAW_RETENTION_DAYS` | Raw check retention, default 7. |
| `UPTIME_HOURLY_RETENTION_DAYS` | Hourly rollup retention, default 90. |
| `UPTIME_DAILY_RETENTION_DAYS` | Daily rollup retention, default 365. |
| `UPTIME_HTTP_USER_AGENT` | User agent sent on checks. |

See `.env.example` for dummy values. Slack/webhook URLs are intentionally not env vars — they are
per-channel data.
