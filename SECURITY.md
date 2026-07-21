# Security policy

## Supported versions

laravel-uptime is at v1 and is released from the `main` branch; there are no tagged releases yet.
Security fixes land on `main`, so the latest `main` is the only supported line. Run against a
recent checkout and keep dependencies current (see `.github/dependabot.yml`).

| Version | Supported |
|---|---|
| `main` (latest) | Yes |
| Older commits | No |

## Reporting a vulnerability

Please report security issues privately — do not open a public issue, pull request, or discussion
for anything security-sensitive.

1. Go to the repository's **Security** tab and choose **Report a vulnerability** to open a private
   GitHub Security Advisory. This keeps the report confidential until a fix is available.
2. Include enough detail to reproduce: affected endpoint or command, the conditions required, and
   the impact you observed.

What to expect:

- An acknowledgement within a few days.
- An assessment of whether the report is accepted, along with a severity judgement.
- A fix on `main` for accepted reports, and credit in the advisory if you would like it.

## Scope notes

This is a self-hosted tool whose operators are trusted (accounts are created only from the CLI via
`php artisan uptime:user`; there is no public registration). Monitor URLs are operator-supplied and
fetched by the worker by design, so fetching an operator-configured target is expected behaviour,
not a vulnerability. Reports about the public status page or its JSON twin (`/status/{slug}` and
`/status/{slug}/json`) leaking monitor URLs, operator identity, or raw error text, about alert
channel URLs or secrets appearing in logs, or about the authentication and throttling surfaces are
all in scope.

Keep `APP_KEY` secret and `APP_DEBUG=false` outside local development: the key encrypts stored
alert-channel configuration, and debug mode exposes stack traces.
