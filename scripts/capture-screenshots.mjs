// Captures the README screenshots from a locally running instance seeded with
// DemoSeeder. Nothing here touches application code; it only drives a browser.
//
//   php artisan migrate:fresh --force
//   php artisan db:seed --class=DemoSeeder --force
//   php artisan serve &                       # http://127.0.0.1:8000
//   node scripts/capture-screenshots.mjs
//
// Requires Playwright, which is not a dependency of this repo (there is no npm
// build). Install it globally (`npm i -g playwright && npx playwright install
// chromium`) and run with NODE_PATH=$(npm root -g).
//
// Override the target with BASE_URL, e.g. when serving via a raw built-in server
// that routes static assets: BASE_URL=http://127.0.0.1:8802 node scripts/...

import playwright from 'playwright';
import { mkdir } from 'node:fs/promises';

const { chromium } = playwright;

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const outDir = 'docs/images';

const shots = [
  { file: 'dashboard.png', path: '/dashboard', height: 900 },
  { file: 'monitor-detail.png', path: '/monitors/2', height: 1180 },
  { file: 'incident-timeline.png', path: '/incidents/2', height: 760 },
  { file: 'status-page.png', path: '/status/acme-cloud', height: 900 },
];

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await context.newPage();

await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
await page.fill('#email', 'demo@example.com');
await page.fill('#password', 'password');
await Promise.all([
  page.waitForURL(`${base}/dashboard`, { waitUntil: 'domcontentloaded' }),
  page.click('button[type=submit]'),
]);

await mkdir(outDir, { recursive: true });

for (const shot of shots) {
  await page.setViewportSize({ width: 1280, height: shot.height });
  await page.goto(`${base}${shot.path}`, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: `${outDir}/${shot.file}` });
  console.log(`captured ${outDir}/${shot.file}`);
}

await browser.close();
