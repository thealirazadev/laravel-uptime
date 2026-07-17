# Engineering Rules — laravel-uptime

These rules are binding for every change in this repository.

## Conventions

- **Framework patterns**: Laravel idioms throughout. Controllers stay thin; validation lives in
  Form Requests; state-machine and domain logic lives on models (`Monitor::applyCheckResult`) or
  the small `app/Support` classes named in `docs/architecture.md`; background work lives in Jobs;
  schedule definitions live in `routes/console.php`. No query logic in routes, middleware, or
  Blade.
- **Preferred libraries**: the framework and the chosen stack only — Eloquent, Laravel HTTP
  client, Laravel Mail, database queue/cache/session, Pest, Pint. Alert channels are plain
  `Http::post` calls; do not add notification-channel packages, chart libraries, admin panels, or
  repositories/DTO libraries.
- **What to avoid**: raw SQL string concatenation; `DB::raw` with user input;
  driver-specific SQL (`DATE()`, `strftime()`) — aggregation must behave identically on SQLite
  and MySQL; sleeps or real network calls in tests (`Http::fake`, `Queue::fake`,
  `Carbon::setTestNow` instead); logic in Blade beyond display; global helpers for domain logic;
  fat controllers.
- **Concurrency invariants (do not weaken)**: the dispatcher claims monitors via conditional
  UPDATE on `next_check_at` and only dispatches on one affected row; `RunHttpCheck` keeps
  `WithoutOverlapping` keyed by monitor id; monitor counters/status are mutated only inside
  `applyCheckResult`. Any change touching these needs owner sign-off.
- **Naming (PSR-12 + Laravel)**: controllers `PascalCaseController`; models singular
  `PascalCase`; jobs imperative `PascalCase` (`RunHttpCheck`); artisan commands namespaced
  `uptime:*`; Form Requests `VerbNounRequest`; tables plural snake_case; columns snake_case;
  routes kebab/lowercase; Blade views snake_case. Pint enforces style.
- **Commit format**: Conventional Commits, short imperative subject — `feat: add queued http
  check job with overlap lock`, `fix: reset ssl thresholds on renewal`, `test: cover alert
  dedup`. One commit per feature or task, exactly as listed in `docs/phases.md`; never batch
  features, never split one small feature into noisy commits.
- **Pin exact dependency versions**: add dependencies with exact versions, commit
  `composer.lock`. No blanket upgrades or "pull latest" without approval; a dependency change is
  its own approved commit.
- **DB migration rule**: every schema change goes through a migration file; never edit a
  migration that has been applied/committed — add a new one. Never change schema by hand.
  `$fillable`/`$casts` changes ship in the same commit as the migration introducing the columns.

## Error handling & logging

- **Every external/fallible call handles failure**: the check HTTP request (failure is a valid
  check result, never an unhandled job exception); the SSL socket/parse (log and keep stale
  values); every alert delivery (retry with backoff, then `alert_failed` event + log); mail send;
  DB writes in jobs. Alert failure on one channel must not affect other channels or the incident
  state.
- **Friendly user errors vs detailed logs**: operators see short flash messages and field errors;
  status page visitors see nothing internal. Full context (exception class, monitor id, reason —
  never channel secrets or webhook URLs) goes to logs only.
- **No stack traces to users**: `APP_DEBUG=false` outside local; custom 404/500 pages; no
  framework debug pages in any environment users touch.
- **One consistent JSON error format** for every JSON response (see `docs/api-contracts.md`):
  `{ "error": { "code": "...", "message": "...", "details": {...} } }` with `details` omitted
  when empty.
- **Structured logging from day one**: context arrays, not interpolation —
  `Log::warning('alert.send_failed', ['channel_id' => $id, 'monitor_id' => $mid, 'reason' => $e->getMessage()])`.
  Dotted event keys: `check.failed`, `monitor.down`, `monitor.up`, `incident.opened`,
  `incident.closed`, `alert.sent`, `alert.send_failed`, `ssl.warning_sent`, `ssl.check_failed`,
  `rollup.completed`, `prune.completed`, `auth.login_failed`.

## Security

- **No hardcoded secrets**: everything sensitive comes from `.env` (git-ignored);
  `.env.example` carries dummies only and stays current. Per-channel Slack/webhook URLs and
  secrets live in `alert_channels.config` under the `encrypted:array` cast — never logged, masked
  when redisplayed in forms.
- **Validate all input server-side** via Form Requests: URL must be `http`/`https`;
  `interval_seconds` from the allowed set; `timeout_seconds` 1–30; `confirmation_threshold` 1–10;
  `expected_status` 100–599; slugs kebab-case unique; channel config validated per type
  (webhook/slack URLs must be valid `https` URLs).
- **SSRF posture**: monitor URLs are entered by authenticated operators of a self-hosted tool, so
  internal targets are allowed by design — but check and webhook responses/bodies are never
  echoed back to any public surface, and the status page never reveals monitor URLs.
- **XSS / injection**: Blade `{{ }}` escaping everywhere; no `{!! !!}` for user-sourced values
  (the SVG chart builder only interpolates numbers it computed). Eloquent/parameter-bound queries
  only.
- **CSRF**: all dashboard forms POST/PUT/DELETE with CSRF tokens (Laravel default; do not exempt
  routes).
- **Rate limiting**: login throttled tightly by IP; public status HTML and JSON throttled by IP.
  Limits and headers per `docs/api-contracts.md`.
- **Protected routes & access**:
  - `GET/POST /login` — public, throttled. `POST /logout` — authenticated.
  - All dashboard routes (`/dashboard`, `/monitors*`, `/channels*`, `/groups*`, `/incidents*`)
    — `auth` middleware; every logged-in operator has full access (single flat team, per PRD).
  - `GET /status/{slug}` and `GET /status/{slug}/json` — public, throttled, cached; only for
    groups with `is_public = true`; expose monitor names and aggregates, never URLs, operator
    identity, or raw error text.
  - Artisan commands — CLI only.

## Simplicity / YAGNI-KISS

- Write the minimum that satisfies the current phase. No speculative features, config flags, or
  parameters nothing needs today.
- Prefer the boring, direct solution over the clever or "scalable" one; prefer built-in framework
  features over reimplementation.
- No abstraction until three real, existing use cases demand it (the `AlertSender` interface
  already has its three: mail, slack, webhook — do not add more seams around it).
- No new wrapper classes, factories, managers, or utils files beyond those named in
  `docs/architecture.md` without owner approval first.
- Before submitting, self-review: "fewer lines without hurting readability?" If yes, rewrite
  first. If a solution exceeds ~150 lines, pause and justify before continuing.

## Code style

- Comments are sparse and explain **why**, not what — the claim UPDATE and the threshold logic
  deserve a comment; a getter does not. Concise docstrings on non-obvious methods only.
- No emoji anywhere: code, comments, commits, docs.
- No mention of AI, assistants, or model/tool names anywhere in the codebase; no generated-by or
  co-authorship attribution lines in commits.
- Conventional Commits, imperative subject, one per feature.
- Pint owns formatting; never hand-format against it.

## Boundaries — never do without asking the owner first

- Never delete or rewrite a file wholesale; targeted edits only, and flag destructive changes
  before making them.
- Never modify `docs/PRD.md` or `docs/architecture.md` without flagging the change and getting
  sign-off — they are the source of truth.
- Never add a dependency without approval (propose what, why, version, size; wait).
- If a task is ambiguous, ask instead of assuming.
- On an error you cannot fix in two attempts, stop and explain what was tried instead of
  thrashing.
- Mid-phase requests not in `docs/PRD.md`: ask whether to (a) add to the current phase, (b)
  create a new phase, or (c) log to the Backlog in `docs/phases.md`. Never silently absorb scope.
