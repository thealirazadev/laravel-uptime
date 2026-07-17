# Design — laravel-uptime

Two surfaces: the authenticated Blade dashboard (dense, functional, for daily operators) and the
public status page (calm, minimal, client-facing). Both share one small hand-written stylesheet;
no CSS framework, no JS beyond a few lines (mobile nav toggle, delete confirms). Charts are
server-rendered inline SVG. Light theme only in v1.

## Color & theme

Neutral base, color reserved for status meaning.

| Token | Value | Use |
|---|---|---|
| `--bg` | `#f8fafc` | Page background |
| `--surface` | `#ffffff` | Cards, tables, forms |
| `--border` | `#e2e8f0` | Card and table borders |
| `--text` | `#0f172a` | Body text |
| `--text-muted` | `#475569` | Secondary text, labels, timestamps |
| `--accent` | `#1d4ed8` | Links, primary buttons, focus rings |
| `--up` | `#15803d` | Up status, success flashes |
| `--down` | `#b91c1c` | Down status, open incidents, destructive buttons, errors |
| `--warn` | `#b45309` | SSL expiry warnings, paused/unknown attention states |
| `--neutral` | `#64748b` | Unknown/paused status |

Status is never conveyed by color alone: every badge pairs color with a text label ("Up",
"Down", "Unknown", "Paused") and charts encode failures by position/label, not hue only.

## Typography

- Font: system stack — `ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`;
  `ui-monospace, SFMono-Regular, Menlo, monospace` for URLs, response times, and error reasons.
- Scale (rem): 0.75 (12px, chart axis labels), 0.875 (14px, table meta), 1 (16px, body — base),
  1.125 (18px, card titles), 1.5 (24px, page titles), 1.875 (30px, status page heading).
- Weights: 400 body, 500 labels/badges, 600 headings. Line height 1.5 body, 1.2 headings.

## Spacing, radius, shadows

- 4/8px spacing system: 4, 8, 12, 16, 24, 32, 48. Card padding 16–24; form field gap 16;
  section gap 32.
- Border radius: 6px (inputs, buttons, cards), 999px (status badges).
- Shadows: cards `0 1px 2px rgb(15 23 42 / 0.06)`; no heavier elevation. Borders do most of the
  separation work.
- Layout: dashboard max-width 1100px; status page max-width 720px, centered. Single column under
  768px; tables scroll horizontally in their own container rather than breaking the page.

## Components and states

- **Buttons** — primary (accent bg, white text), secondary (surface bg, border), destructive
  (`--down`). Hover: darken ~8%. Focus: 2px outline `--accent` with 2px offset (never removed).
  Disabled: 50% opacity, `cursor: not-allowed`. Loading: label swaps to "Saving…" and the button
  disables (form re-submission guard).
- **Inputs/selects/checkboxes** — 1px `--border`, radius 6, padding 8/12. Focus: accent border +
  ring. Error: `--down` border plus a message under the field referencing the input via
  `aria-describedby`. Disabled: muted bg. Every input has a visible `<label>`.
- **Status badge** — pill, 500 weight, colored bg at ~12% tint with solid colored text
  (up/down/warn/neutral variants), always with the text label.
- **Tables** (monitors, incidents, channels) — header row muted 14px, row hover tint,
  row-level actions as text links. Empty state: centered muted message plus a primary action
  ("Add your first monitor").
- **Flash messages** — top of content: success (up tint), error (down tint), dismiss via page
  navigation (no JS toast system).
- **Incident timeline** — vertical list, dot per event colored by type (opened `--down`, alerts
  `--neutral`/`--warn` when failed, closed `--up`), monospace timestamps.
- **Charts (inline SVG)** — response-time line/area over hourly or daily rollups; uptime as a
  segmented bar (one segment per bucket: up tint / down tint / gray for no data). Axis labels
  12px muted; failed buckets get a visible marker, not just color. Each SVG has `role="img"` and
  an `aria-label` summarizing the window ("Average response time last 24 hours: 182 ms, range
  120–410"). A brand-new monitor renders a "Not enough data yet" empty state instead of an empty
  chart.
- **Loading** — server-rendered pages have no client loading states; slow actions (test alert)
  return via redirect + flash. Queued outcomes are labeled honestly ("Test alert queued —
  check the channel").

## Status page (public) specifics

- Header: group name, overall state sentence ("All systems operational" / "Some systems are
  down"), generated-at timestamp.
- One row per monitor: name, status badge, uptime percentages (24 h / 7 d / 30 d), average
  response time. No URLs, no error strings, no operator identity.
- Recent incidents (14 days): monitor name, started/resolved times, duration. Open incidents
  first, highlighted with the down tint.
- No JavaScript at all on this page. Fast, self-contained, cacheable.

## Error pages (404 / 500)

Same rules as the status page: `<!doctype html>`, `lang="en"`, single `<main>` with an `<h1>`
naming the state, plain-language explanation, link back to `/` with descriptive text, correct
HTTP status from the response (not the template), AA contrast, readable on mobile, no JS.

## Accessibility baseline (both surfaces)

- Semantic HTML: `<header>`, `<nav>`, `<main>`, one `<h1>` per page, ordered heading levels,
  real `<table>` markup with `<th scope>`, `<button>`/`<a>` used for their actual semantics.
- Every form input has a `<label>`; errors are announced via `aria-describedby`; destructive
  actions are forms with confirmation, not bare links.
- Fully keyboard-navigable: logical DOM order, visible focus states everywhere, no traps, skip
  link on the dashboard shell.
- Color contrast meets WCAG AA (4.5:1 body text; badge tint/solid pairs checked); status and
  chart information never color-only.
- `<meta name="viewport">` on every page; base font 16px; touch targets at least 40px tall.
