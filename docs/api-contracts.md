# API Contracts — laravel-uptime

Base URL: value of `APP_URL`. This project has no management REST API — the dashboard is
server-rendered Blade with session auth and CSRF (routes summarized below). Full contract detail
covers the two machine-readable surfaces: the public status JSON endpoint and the outbound alert
payloads (Slack and generic webhook). This contract is agreed before any code is written.

## Conventions

- Timestamps are ISO-8601 UTC (`Z` suffix). Durations are integer seconds.
- JSON success responses wrap payloads in `data`. JSON errors use the single error envelope.
- Uptime percentages are numbers rounded to two decimals (e.g. `99.98`); `null` means not enough
  data for the window.

## Error envelope (every JSON error)

```json
{
  "error": {
    "code": "not_found",
    "message": "Status page not found."
  }
}
```

`details` (object) is added only when there is field-level information; it is omitted otherwise.

### Stable error codes

| HTTP | `error.code` | When |
|---|---|---|
| 404 | `not_found` | Unknown slug, or the group is not public. Identical response for both — existence is not leaked. |
| 429 | `rate_limited` | Throttle limit exceeded. |
| 500 | `server_error` | Unexpected error (details logged, never returned). |

---

## Public status endpoints

### GET /status/{slug}  (public, throttled, cached ~60 s)

HTML status page for a public monitor group. Unknown or non-public slug renders the 404 page.
Never exposes monitor URLs, operator identity, or raw error text.

### GET /status/{slug}/json  (public, throttled, cached ~60 s)

Response `200`:

```json
{
  "data": {
    "group": { "name": "Client A", "slug": "client-a" },
    "generated_at": "2026-07-18T10:00:00Z",
    "overall": "operational",
    "monitors": [
      {
        "name": "Client A website",
        "status": "up",
        "last_checked_at": "2026-07-18T09:59:12Z",
        "uptime": { "day": 100.0, "week": 99.95, "month": 99.98 },
        "avg_response_time_ms": { "day": 182 }
      },
      {
        "name": "Client A API",
        "status": "down",
        "last_checked_at": "2026-07-18T09:58:40Z",
        "uptime": { "day": 97.2, "week": 99.6, "month": null },
        "avg_response_time_ms": { "day": 410 }
      }
    ],
    "incidents": [
      {
        "monitor": "Client A API",
        "status": "open",
        "started_at": "2026-07-18T09:41:00Z",
        "closed_at": null,
        "duration_seconds": 1140
      },
      {
        "monitor": "Client A website",
        "status": "resolved",
        "started_at": "2026-07-11T02:10:00Z",
        "closed_at": "2026-07-11T02:22:00Z",
        "duration_seconds": 720
      }
    ]
  }
}
```

Rules:

- `overall` is `operational` (all listed monitors `up`), `down` (at least one `down`), or
  `unknown` (no monitor confirmed yet). Paused monitors are excluded from the page entirely.
- `monitors` are the group's monitors ordered by name. `status` is `up | down | unknown`.
- `uptime` windows: `day` = last 24 h (hourly rollups), `week` = 7 d, `month` = 30 d (daily
  rollups). `null` when the window has no rollup data yet.
- `incidents` lists incidents that started within the last 14 days for the group's monitors,
  newest first, open first. `duration_seconds` for open incidents is measured to `generated_at`.
- Errors: `404 not_found` (unknown/non-public slug), `429 rate_limited`, `500 server_error`.

---

## Outbound alert payloads

### Generic webhook channel

`POST` to the channel's configured URL. Timeout 10 s; retried per the `SendAlert` policy
(3 tries, backoff 60 s / 300 s). A 2xx response is success; anything else is a failed attempt.

Headers:

| Header | Value |
|---|---|
| `Content-Type` | `application/json` |
| `User-Agent` | `UPTIME_HTTP_USER_AGENT` value |
| `X-Uptime-Event` | `incident.opened`, `incident.closed`, `ssl.expiry_warning`, or `test` |
| `X-Uptime-Signature` | `sha256=<hex hmac-sha256 of the raw body, keyed by the channel secret>` — present only when the channel has a secret configured |

Body — `incident.opened`:

```json
{
  "event": "incident.opened",
  "sent_at": "2026-07-18T09:43:05Z",
  "monitor": { "id": 12, "name": "Client A API", "url": "https://api.client-a.example", "status": "down" },
  "incident": {
    "id": 88,
    "started_at": "2026-07-18T09:41:00Z",
    "closed_at": null,
    "duration_seconds": null,
    "summary": "status_mismatch:503"
  }
}
```

Body — `incident.closed`: same shape with `monitor.status = "up"`, `closed_at` set, and
`duration_seconds` filled.

Body — `ssl.expiry_warning`:

```json
{
  "event": "ssl.expiry_warning",
  "sent_at": "2026-07-18T03:00:10Z",
  "monitor": { "id": 12, "name": "Client A API", "url": "https://api.client-a.example", "status": "up" },
  "ssl": { "expires_at": "2026-08-10T00:00:00Z", "days_left": 22, "threshold_days": 30 }
}
```

Body — `test`: `{"event": "test", "sent_at": "...", "message": "Test alert from laravel-uptime."}`.

Notes: webhook payloads include the monitor URL (the receiver is operator-configured and
trusted); the public status JSON never does. `incident` and `ssl` keys are present only for
their event types.

### Slack incoming-webhook channel

`POST` to the channel's webhook URL with Slack's minimal text format. Same retry policy.

```json
{ "text": "DOWN: Client A API (https://api.client-a.example) — status_mismatch:503. Since 2026-07-18 09:41 UTC." }
```

Message templates: `DOWN: {name} ({url}) — {summary}. Since {started_at}.` /
`RECOVERED: {name} ({url}). Down {duration}.` /
`SSL: certificate for {name} expires in {days_left} days ({expires_at}).` /
`Test alert from laravel-uptime.`

### Mail channel

To the channel's configured address, from `MAIL_FROM_*`. Subjects:
`[laravel-uptime] DOWN: {name}`, `[laravel-uptime] RECOVERED: {name}`,
`[laravel-uptime] SSL expiry: {name} ({days_left} days)`, `[laravel-uptime] Test alert`.
Body: `emails/alert.blade.php` — monitor name, URL, event detail, link to the dashboard
monitor page.

---

## Dashboard routes (summary — server-rendered, session auth + CSRF)

| Method + path | Auth | Purpose |
|---|---|---|
| GET /login, POST /login | public, throttled 5/min/IP | Login form / attempt. Failure: generic message, no user enumeration. |
| POST /logout | auth | End session. |
| GET /dashboard | auth | Overview: all monitors with status badges, open incidents. |
| GET /monitors, GET /monitors/create, POST /monitors | auth | List / create (fields per `docs/architecture.md`, incl. group and channel checkboxes). |
| GET /monitors/{id}, GET /monitors/{id}/edit, PUT /monitors/{id}, DELETE /monitors/{id} | auth | Detail (charts, checks, incidents, SSL state) / edit (incl. pause) / delete (cascades). |
| GET /channels, GET /channels/create, POST /channels, GET /channels/{id}/edit, PUT /channels/{id}, DELETE /channels/{id} | auth | Alert channel CRUD; secrets masked on edit. |
| POST /channels/{id}/test | auth | Queue a test alert; flash the outcome. |
| GET /groups, GET /groups/create, POST /groups, GET /groups/{id}/edit, PUT /groups/{id}, DELETE /groups/{id} | auth | Monitor group CRUD. |
| GET /incidents, GET /incidents/{id} | auth | Incident list (open first) / timeline detail. |

Validation failures re-render the form with field errors (standard Laravel session flash). All
state-changing routes require CSRF. There are no JSON dashboard endpoints in v1.

---

## Rate limiting

| Surface | Limit (key) |
|---|---|
| POST /login | 5/min per IP |
| GET /status/{slug} and /status/{slug}/json | 60/min per IP |

Throttled responses return `429` — the JSON endpoint uses the error envelope with
`rate_limited`; HTML surfaces use a plain 429 page. `X-RateLimit-Limit`,
`X-RateLimit-Remaining`, and (on 429) `Retry-After` headers are set. Exact numbers are config;
the shape is fixed here.
