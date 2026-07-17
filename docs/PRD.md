# Product Requirements — laravel-uptime

## What we're building

A self-hosted monitoring app for people who manage other people's websites. An operator logs into a
Blade dashboard, adds monitors (URL, check interval, expected status/keyword), and the app takes it
from there: the Laravel scheduler dispatches queued HTTP checks, records response times, checks SSL
certificate expiry with advance warnings, and drives an incident lifecycle — an incident opens only
after N consecutive failed checks, stays open without re-alerting, and closes (with a recovery
alert) after N consecutive successes. Alerts route per monitor to mail, Slack incoming webhooks, or
a signed generic webhook. Monitors can be grouped, and each group gets a public status page (HTML
and JSON) showing current status, uptime percentages, and recent incidents. Raw check results are
pruned on a schedule and rolled up into hourly/daily aggregates so history stays queryable forever
without unbounded growth.

## Target user

A freelancer or small agency responsible for a portfolio of client sites (typically 5–100). They
want one boring, self-hosted tool that tells them a site is down or a certificate is about to
expire before the client does, and a status page URL they can hand to each client. Single team, no
tenants: everyone who can log in sees everything.

## Core features (prioritized)

1. **Monitor CRUD** — Create, edit, pause, and delete monitors from the dashboard. A monitor has a
   name, an `http`/`https` URL, a check interval (1, 5, 15, 30, or 60 minutes), a request timeout,
   an expected HTTP status (default 200), an optional expected keyword in the response body, and a
   confirmation threshold N (default 2). Highest priority: nothing else exists without monitors.

2. **Scheduled queued checks with overlap safety** — Every minute the scheduler claims due monitors
   with an atomic per-row update and dispatches one queued check job per monitor. Jobs run on the
   database queue and carry an overlap lock keyed by monitor id, so workers scale horizontally
   without two workers ever checking the same monitor concurrently and without a monitor being
   double-dispatched by two scheduler ticks.

3. **Incident lifecycle with confirmation thresholds (flap suppression)** — A monitor transitions
   up→down only after N consecutive failed checks, and down→up only after N consecutive successful
   checks. The down transition opens an incident (recording when the failure streak actually
   started); the up transition closes it. Each incident keeps a timeline of events (opened, alerts
   sent, closed). A flapping site below the threshold changes nothing and alerts no one.

4. **Alert channels with per-monitor routing and de-duplication** — Reusable alert channels of type
   mail, Slack incoming webhook, or generic webhook (JSON POST, optional HMAC signature). Each
   monitor selects which channels it alerts. Exactly one alert per channel fires when an incident
   opens and one when it closes; an already-open incident never re-alerts. A "send test" action
   verifies a channel end to end.

5. **SSL certificate expiry monitoring** — For `https` monitors, a daily queued check reads the
   certificate's expiry date. Warnings go out through the monitor's channels at configurable
   thresholds (default 30, 14, 7 days, plus expiry itself), at most once per threshold per
   certificate; a renewed certificate resets the warnings.

6. **Response-time history with retention and rollups** — Every check stores a raw row (ok/fail,
   HTTP status, response time, error). Hourly and daily scheduled jobs roll raw rows up into
   per-monitor aggregates (total, failed, avg/min/max response time), then prune raw rows past the
   raw-retention window and hourly rollups past theirs. Charts and uptime percentages read from
   rollups, never from unbounded raw scans.

7. **Public status pages** — Monitors can belong to a monitor group with a slug. `GET
   /status/{slug}` serves an unauthenticated, cached HTML page (plus a JSON twin) with each
   monitor's current status, uptime over 24 h / 7 d / 30 d, average response time, and recent
   incidents. No internal detail (URLs are optional per group setting — v1 shows monitor names
   only).

8. **Dashboard** — Blade, session auth, deliberately boring: overview of all monitors with status
   badges, monitor detail with response-time chart and incident history, channel and group
   management, incident list. Users are created with an artisan command; there is no public
   registration.

## Non-goals

- Multi-tenancy, teams, roles, or per-client logins. One flat operator account pool.
- Billing, subscriptions, or any commercial plumbing.
- Server-agent monitoring (CPU, RAM, disk) — this watches URLs from the outside only.
- A mobile app or native notifications.
- Check types beyond HTTP(S): no ICMP ping, TCP port, DNS, or browser/synthetic checks.
- Domain-name (WHOIS) expiry checks — SSL expiry only in v1.
- Maintenance windows / scheduled downtime suppression.
- SMS or push alert channels; paging/escalation policies (the generic webhook is the escape hatch).
- Status page custom domains, theming, or subscriber email lists.
- A management REST API — the dashboard is the only write surface; JSON is read-only status data.
- Real-time updates (websockets); pages refresh on load.
- Redis, Horizon, or any infrastructure beyond PHP + one database.

## Success criteria per core feature

- **Monitor CRUD** — An operator can create a monitor and see it listed with status `unknown`;
  invalid input (bad URL, unsupported interval, out-of-range threshold) returns field-level
  validation errors and persists nothing; pausing a monitor stops new checks within one scheduler
  tick; deleting a monitor removes its checks, rollups, and incidents.
- **Scheduled queued checks** — With two workers running against a due monitor, exactly one check
  row is written per interval (verified by the atomic-claim test and the overlap-lock test); a
  monitor with a 5-minute interval accumulates ~12 checks per hour, not 24; a stopped queue delays
  checks but never duplicates them once drained.
- **Incident lifecycle** — With N=2, a single failed check changes nothing; the second consecutive
  failure flips the monitor to `down` and opens exactly one incident whose `started_at` matches the
  first failure of the streak; two consecutive successes close it and record a `closed` event; a
  fail/ok/fail/ok sequence produces zero incidents and zero alerts.
- **Alerts** — Opening an incident sends exactly one alert per attached enabled channel (asserted
  via queue/HTTP fakes); 50 further failed checks on the open incident send zero additional alerts;
  closing sends exactly one recovery alert per channel; a channel delivery failure is retried,
  then logged and recorded as an `alert_failed` timeline event without affecting other channels;
  "send test" delivers a test payload to the real channel.
- **SSL expiry** — A certificate expiring in 20 days triggers exactly one 30-day warning and no
  others; when it drops to 10 days, exactly one 14-day warning; re-running the daily check sends
  nothing new; after renewal (later expiry date), thresholds re-arm; an expired certificate warns
  once at expiry.
- **Retention and rollups** — After the rollup jobs run, hourly and daily aggregate rows exist
  whose totals equal the raw rows they cover (idempotent on re-run via upsert); raw checks older
  than the raw window and hourly rollups older than the hourly window are gone; uptime percentages
  on the dashboard and status page match hand-computed values from the rollups.
- **Public status pages** — An unauthenticated visitor sees the HTML page for a public group slug
  and correct per-monitor status/uptime numbers; the JSON twin returns the documented contract; an
  unknown or non-public slug returns 404 in the documented error format; responses are cached
  (about 60 s) and rate limited; no monitor URL, operator identity, or internal error text leaks.
- **Dashboard** — Login required for every dashboard route (unauthenticated requests redirect to
  the login form); wrong credentials show a safe message; the monitor detail chart renders from
  rollups and matches the recorded data; empty states (no monitors, no incidents, no channels)
  render with guidance instead of blank tables.
