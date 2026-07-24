---
name: Bug report
about: Report a defect in monitoring, alerts, SSL checks, rollups, or the status pages
title: "bug: "
labels: bug
assignees: ''
---

## Summary

A clear, concise description of what is wrong.

## Steps to reproduce

1. Go to '...'
2. Run '...' (for example `php artisan schedule:work`, a specific monitor config)
3. See the incorrect behavior

## Expected behavior

What you expected to happen.

## Actual behavior

What actually happened. Paste relevant **structured log lines** (e.g. `check.failed`,
`incident.opened`, `alert.send_failed`) with their context, but **redact any alert-channel URLs or
secrets** first — they must never appear in an issue.

## Which surface

- [ ] HTTP/uptime check (`RunHttpCheck`)
- [ ] Incident lifecycle / confirmation threshold
- [ ] Alerts (mail / Slack / webhook)
- [ ] SSL expiry check
- [ ] Rollups / retention / pruning
- [ ] Dashboard (authenticated)
- [ ] Public status page or JSON twin
- [ ] Other

## Environment

- laravel-uptime commit / branch:
- PHP version (`php -v`):
- Database: [ ] SQLite (dev) [ ] MySQL 8 (prod)
- Queue/scheduler running? (`queue:work`, `schedule:work` / cron):

## Additional context

Anything else that helps — screenshots (with monitor URLs cropped out), timing, config values.
