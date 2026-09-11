// Gate 10 (PLAN.md Task 8.2) — static no-polling source gate.
//
// Fails (exit 1) if any product-state polling construct reappears in the tracked source:
//   - setInterval(...) in frontend runtime source or Blade views (product-state discovery timer)
//   - retired public endpoints /dashboard/public, /config/public
//   - retired periodic outbox relays (ingestion:relay-outbox, domain:relay-outbox, --interval sweep)
//
// It intentionally does NOT flag:
//   - XREADGROUP BLOCK / XAUTOCLAIM (blocking reads + lease recovery are not polling)
//   - one-shot lifecycle recovery calls (/latest-readings, /alerts/active, /devices/status-snapshot
//     are legitimate one-shot GETs; only a setInterval wrapper around them would be caught)
//   - UI-only setTimeout (toast/debounce), CHOKIDAR_USEPOLLING (dev filesystem watch)
//   - *.test.js / *.spec.js (fake-timer strings like toFake:['setInterval'] are test scaffolding)
//
// Run: node scripts/verify-no-polling.mjs  (npm run audit:no-polling:source)
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const REPO = path.resolve(__dirname, '..', '..');
const FRONT = path.resolve(__dirname, '..');

const violations = [];

function walk(dir, exts, onFile) {
  if (!fs.existsSync(dir)) return;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === 'node_modules' || entry.name === 'vendor' || entry.name.startsWith('.')) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, exts, onFile);
    else if (exts.some((e) => entry.name.endsWith(e))) onFile(full);
  }
}

function isTestFile(file) {
  return /\.(test|spec)\.(js|ts|mjs)$/.test(file);
}

// 1. setInterval in frontend runtime source (exclude tests) + Blade views.
const SET_INTERVAL = /\bsetInterval\s*\(/;
walk(path.join(FRONT, 'src'), ['.js', '.ts', '.vue', '.mjs'], (file) => {
  if (isTestFile(file)) return;
  const src = fs.readFileSync(file, 'utf8');
  if (SET_INTERVAL.test(src)) violations.push(`${path.relative(REPO, file)}: setInterval() — product-state polling timer`);
});
walk(path.join(REPO, 'back', 'resources', 'views'), ['.php', '.blade.php'], (file) => {
  const src = fs.readFileSync(file, 'utf8');
  if (SET_INTERVAL.test(src)) violations.push(`${path.relative(REPO, file)}: setInterval() in Blade view — periodic state discovery`);
});

// 2. Retired public endpoints anywhere in frontend runtime source.
const RETIRED_ENDPOINTS = ['/dashboard/public', '/config/public'];
walk(path.join(FRONT, 'src'), ['.js', '.ts', '.vue', '.mjs'], (file) => {
  if (isTestFile(file)) return;
  const src = fs.readFileSync(file, 'utf8');
  for (const ep of RETIRED_ENDPOINTS) {
    if (src.includes(ep)) violations.push(`${path.relative(REPO, file)}: references retired public endpoint ${ep}`);
  }
});

// 3. Retired periodic outbox relays in backend commands + compose.
const RELAY_TOKENS = ['ingestion:relay-outbox', 'domain:relay-outbox', 'relay-outbox --interval'];
const backendRoots = [
  path.join(REPO, 'back', 'app', 'Console', 'Commands'),
  path.join(REPO, 'back', 'app', 'Jobs'),
];
for (const root of backendRoots) {
  walk(root, ['.php'], (file) => {
    const src = fs.readFileSync(file, 'utf8');
    for (const tok of RELAY_TOKENS) {
      if (src.includes(tok)) violations.push(`${path.relative(REPO, file)}: retired relay construct "${tok}"`);
    }
  });
}
const compose = path.join(REPO, 'docker-compose.yml');
if (fs.existsSync(compose)) {
  const src = fs.readFileSync(compose, 'utf8');
  for (const tok of RELAY_TOKENS) {
    if (src.includes(tok)) violations.push(`docker-compose.yml: retired relay construct "${tok}"`);
  }
}

if (violations.length > 0) {
  console.error('NO-POLLING GATE: FAIL\n');
  for (const v of violations) console.error('  - ' + v);
  console.error(`\n${violations.length} violation(s).`);
  process.exit(1);
}

console.log('NO-POLLING GATE: PASS — no product-state polling constructs in tracked source.');
process.exit(0);
