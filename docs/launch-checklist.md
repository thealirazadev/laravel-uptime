# Launch Checklist — laravel-uptime

Work top to bottom before going to production. Nothing is checked until verified in the target
environment.

## Environment & configuration

- [ ] Production `.env` created from `.env.example` with real values (no dummies committed).
- [ ] `APP_KEY` generated for production — and understood as the encryption key for stored
      alert-channel configs (rotating it orphans them).
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `APP_URL` set to the real host.
- [ ] `DB_*` points at production MySQL 8; credentials stored securely, not in the repo.
- [ ] `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database` confirmed.
- [ ] `UPTIME_*` retention and SSL-threshold values reviewed for this install.
- [ ] Config/route/view caches warmed (`config:cache`, `route:cache`, `view:cache`).

## Process supervision (the app is only alive if these run)

- [ ] Cron entry for `php artisan schedule:run` every minute, verified firing.
- [ ] `php artisan queue:work` under supervisord (or equivalent) with restart-on-exit; worker
      count decided; `--max-time` set so workers pick up deploys.
- [ ] Deploy procedure runs `php artisan migrate --force` and restarts workers
      (`queue:restart`).
- [ ] `failed_jobs` table monitored; a retry/triage routine exists.

## Security

- [ ] No secrets committed; `.env` git-ignored; only `.env.example` tracked.
- [ ] HTTPS enforced; HTTP redirects to HTTPS; secure session cookies.
- [ ] Login throttle active; no registration route exists; operator accounts created via
      `uptime:user` and reviewed.
- [ ] Status pages verified to leak no monitor URLs, error text, or operator identity.
- [ ] Alert-channel secrets confirmed encrypted at rest and masked in forms; grep production
      logs for webhook URLs/secrets — none present.
- [ ] All forms CSRF-protected; all input validated via Form Requests.

## Reliability & observability

- [ ] Error tracking / log aggregation wired up and receiving events.
- [ ] Structured events verified in production logs: `check.*`, `monitor.*`, `incident.*`,
      `alert.*`, `ssl.*`, `rollup.completed`, `prune.completed`.
- [ ] One real end-to-end alert fired per channel type (mail, Slack, webhook) from production.
- [ ] Mail deliverability: SPF/DKIM/DMARC for the from-domain; alert mail lands in inboxes.
- [ ] Rollup and prune jobs observed completing on schedule; `checks` table size plateaus.
- [ ] Database backups scheduled; a restore tested at least once.
- [ ] The monitor host itself is monitored externally (a watcher for the watcher — even a free
      third-party ping on `APP_URL`).

## Pages & responses

- [ ] 404 and 500 pages return correct statuses and render safely with `APP_DEBUG=false`.
- [ ] Status pages: correct data, ~60 s cache confirmed, 429 behavior confirmed, mobile
      rendering checked.
- [ ] Dashboard checked on mobile (nav, tables scroll in-container, forms usable).
- [ ] Empty states verified on a fresh production install (no monitors/channels/groups).
- [ ] Loading/queued states honest everywhere (test alert, slow forms).

## Quality gates

- [ ] `php artisan test` green in CI against the release commit.
- [ ] `./vendor/bin/pint --test` clean.
- [ ] `composer.lock` committed; deployed build matches it.
- [ ] Phase 3 MySQL sanity pass repeated against the production schema.

## Project-specific

- [ ] Two workers run side by side in production with no duplicate checks (verified via check
      counts per interval).
- [ ] Scheduler overlap safe: two consecutive ticks under load produce no double dispatch.
- [ ] A deliberate outage on a test monitor produces exactly one alert per channel, and exactly
      one recovery alert.
- [ ] An almost-expired test certificate produces the expected threshold warning once.
- [ ] Timezone audit: all stored times UTC; status page and alerts render UTC consistently.
