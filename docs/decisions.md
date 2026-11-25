# Design decisions

The engineering choices behind laravel-uptime, the reasons for them, and the bugs and edge cases
found along the way. Each one is reflected in the code referenced in its section.

## 1. PHP and one database, nothing else

The queue, cache and session drivers all use the database, and the `cache_locks` table provides the
atomic locks behind `WithoutOverlapping` and the scheduler's `onOneServer`. A self-hosted monitor
for a freelancer or small agency watches hundreds of URLs, not hundreds of thousands, so Redis would
add an operational dependency without changing anything a user would notice. It stays a config
change for a deployment that outgrows the database. Development runs on SQLite with no services;
production targets MySQL 8 for concurrent writes from several workers.

## 2. Two independent layers stop a monitor being checked twice

`uptime:dispatch-checks` runs every minute. It reads the due monitors against one captured `now`,
then claims each with a conditional
`UPDATE monitors SET next_check_at = ... WHERE id = ? AND next_check_at <= ?` and dispatches
`RunHttpCheck` only when exactly one row was affected. A racing tick matches zero rows and does
nothing. No transaction spans rows and no lock is taken, and the statement behaves the same on
SQLite and MySQL. The scheduler entry adds `onOneServer()` and `withoutOverlapping()` so a
multi-server cron or a slow tick does not stack up, but the per-row claim is the real guarantee.

The job adds a second layer: `WithoutOverlapping($monitorId)` with `dontRelease()`, so a duplicate
job for a monitor that is already being checked is dropped rather than requeued. Either layer alone
covers most cases; together they make two concurrent checks of one monitor impossible across
retries and servers.

## 3. The overlap lock lasts as long as the slowest possible check

Bug found in review: the lock originally expired after 60 seconds, but a check follows up to five
redirects, each with its own timeout of up to 30 seconds, so a worst-case check runs for
`(1 + 5) * 30 = 180` seconds. Once the lock expired, the next tick's job could take it and check the
same monitor concurrently; the dispatcher claim does not prevent this because it advances
`next_check_at` at dispatch time.

The fix sizes the lock from the real bound instead of capping total request time:
`RunHttpCheck::lockSeconds()` is `(1 + MAX_REDIRECTS) * MAX_TIMEOUT_SECONDS + 15`, with both bounds
defined once on `Monitor` and the form requests validating the timeout against the same constant,
so the invariant cannot drift. `dontRelease()` frees the lock as soon as a normal check finishes,
so the long expiry only matters as the ceiling for a crashed worker. The SSL job reads a certificate
with a fixed connect timeout and follows no redirects, so its 60-second lock is ample.

## 4. A failing target is a result, not a job failure

`RunHttpCheck` has `$tries = 1` and catches every throwable from the HTTP call, classifying it as
`timeout`, `tls_error` or `connection_failed`. A wrong status becomes `status_mismatch:<code>`, and
a missing keyword becomes `keyword_missing`; the keyword search reads only the first 256 KB of the
body so a huge response cannot exhaust memory. An over-long redirect chain is recorded as a failed
check rather than crashing the job. The check can fail; the job cannot.

## 5. One state machine owns status, incidents and alerts

Status, counters, incidents and alerts change only inside `Monitor::applyCheckResult`. A monitor
goes down after `confirmation_threshold` consecutive failures and up after the same number of
successes, so a single blip changes nothing. The incident's `started_at` is the time of the first
failure in the streak, not the moment the threshold confirmed it, so incident durations are honest.
`unknown` to `up` is silent; only `down` to `up` closes an incident.

Alert de-duplication is structural: incident alerts are dispatched only from the open and close
transitions, so a failed check against an already-open incident records a check row and nothing
else. There is no "already alerted" flag to keep in sync.

Edge case: the consecutive success and failure counters saturate at the threshold. Past it they
affect no transition, and letting them grow would eventually overflow the `unsignedSmallInteger`
column on a long-lived monitor.

## 6. Alert delivery is retried, isolated, and never leaks secrets

Each channel gets its own `SendAlert` job (`tries` 3, backoff 60 and 300 seconds), so one failing
channel never blocks another. The job carries an `AlertPayload` of plain arrays rather than Eloquent
models, so a queued alert serializes stable data and the mail, Slack and webhook senders all read
the same facts.

Slack and webhook URLs and the webhook secret are per-channel data stored with an `encrypted:array`
cast, never env vars. The queue worker logs the thrown exception on every failed attempt, so
`SendAlert` catches the sender's exception and rethrows a sanitized one that keeps only the HTTP
status and channel id. `failed()` records an `alert_failed` timeline event after the last retry.
On the edit screen secret fields are never pre-filled (a blank field keeps the stored value), and a
channel's type cannot change after creation so its config shape cannot drift.

The generic webhook serializes the body once and signs those exact bytes:
`X-Uptime-Signature: sha256=<hmac of body with the channel secret>`, omitted when no secret is set.

## 7. SSL expiry warns through alerts and never opens an incident

A daily job reads each https monitor's certificate expiry. Thresholds come from
`UPTIME_SSL_WARN_DAYS` (default 30, 14, 7) plus an implicit 0 for expiry itself. The monitor stores
the smallest threshold already warned in `ssl_notified_days` and warns only when the certificate
moves into a tighter one, so each threshold fires once per certificate. A renewal, seen as the
expiry moving later, resets the watermark. A failed TLS read keeps the previous values and logs
without alerting, because reachability belongs to the HTTP check. Expiry is deliberately not an
incident: incidents mean downtime, and counting certificate warnings as outages would distort uptime
figures and the incident timeline.

## 8. Rollups are bucketed in PHP and always run before pruning

`uptime:rollup hour` aggregates raw checks from completed hours, and `uptime:rollup day` aggregates
completed hourly rows. Buckets are computed with Carbon in PHP rather than `DATE()` or `strftime()`,
which differ between SQLite and MySQL, and rows are upserted on `(monitor_id, period, period_start)`
so a re-run is idempotent. The daily average weights each hour's average by its check count; totals
and uptime stay exact.

The schedule orders the daily rollup at 00:15 before the prune at 00:20, so nothing is deleted
before it is aggregated. Retention defaults to 7 days of raw checks, 90 days of hourly rollups and
365 days of daily rollups, which bounds every table. Charts, status pages and uptime percentages
all read rollups instead of scanning raw rows, since the hourly rollup cost grows with raw volume.

Bug found in review: the prune cut at exactly N days back, which at 00:20 falls twenty minutes into
an hour (and into a day for hourly rows). Because each rollup re-aggregates every bucket it still
finds source rows for, the next run overwrote a complete rollup with the part that survived the
prune, so old hourly and daily figures drifted low. Prune now cuts raw checks at the start of the
hour and hourly rollups at the start of the day, which keeps data up to one hour or one day longer
than the nominal window.

## 9. Maintenance windows suppress both transitions

A maintenance window targets monitors directly or through their group. While one is active,
`applyCheckResult` still records the check and advances the counters, but skips both the down and
the up transition, so no incident opens or closes and no alert fires. Suppressing recovery as well
as failure means a monitor that was already down keeps its incident through the window instead of
emitting a recovery alert mid-maintenance. Because the counters keep saturating, the first
qualifying check after the window ends transitions immediately and alerting resumes with no manual
step. A window has one start and one end; recurrence is a free-text note rather than a scheduling
engine. Times are stored and entered in UTC, like the rest of the app.

## 10. Public status pages share one cached builder and leak nothing

Each public group is served as HTML at `/status/{slug}` and as JSON at `/status/{slug}/json`. Both
read one data array cached for 60 seconds per group, so they cannot disagree. Unknown and
non-public slugs return the same 404, so a visitor cannot probe which private groups exist, and
neither surface emits monitor URLs, operator identity or raw error text. Uptime percentages are
derived at read time from rollups and are null when a window has no data.

The JSON endpoint always answers with the error envelope. Bug found along the way: a global
exception renderer runs before the framework's own handling of `HttpResponseException`, which is how
the throttle delivers its 429, so the renderer must return null for it or it turns throttled
responses into 500s.

## 11. Two named rate limiters

`AppServiceProvider` defines `login` at 5 requests per minute per IP and `status` at 60 per minute
per IP, shared by the HTML and JSON status pages. A JSON 429 returns the `rate_limited` envelope and
an HTML 429 renders the custom error page. There is no public registration; operators are created
with `php artisan uptime:user`.

## 12. Charts are server-rendered SVG

Response-time and uptime charts are inline SVG built by `Support/Chart` from rollup rows, with no
chart library and no npm build. That gives up tooltips and zoom, which a "is it up and how slow"
chart does not need. The chart builder interpolates only numbers it computes, which is why it is the
one place output is rendered unescaped with `{!! !!}`. Failures are marked by position and marker
rather than colour alone, and a new monitor shows a "Not enough data yet" state instead of an empty
axis.

Blade edge case found while building the forms: an inline `@end*` directive directly after a word
character, such as `disabled@endunless`, is not compiled, which leaves the condition unclosed.
Inline conditionals use a `{{ }}` ternary instead, and attribute lists that use `@error` keep a
space before the directive.
