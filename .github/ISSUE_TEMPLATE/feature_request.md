---
name: Feature request
about: Suggest an improvement for laravel-uptime
title: "feat: "
labels: enhancement
assignees: ''
---

## Problem

What are you trying to do that the app does not support today? Describe the concrete situation, not
just the solution.

## Proposed solution

What you would like to happen. Keep it in the spirit of the project: a self-hosted tool that needs
only PHP and one database, with no new runtime dependencies (no Redis, chart libraries, or
notification-channel packages) unless clearly justified.

## Alternatives considered

Other approaches you thought about and why they fall short.

## Scope check

- [ ] This fits a small, single-purpose change (roughly one commit's worth of work)
- [ ] It does not weaken the concurrency invariants (dispatcher claim, per-monitor overlap lock)
- [ ] It does not expose monitor URLs, operator identity, or secrets on the public status pages

## Additional context

Anything else - links, examples from similar tools, mockups.
