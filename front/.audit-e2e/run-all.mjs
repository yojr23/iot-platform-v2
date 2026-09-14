// Stage 0 (PLAN.md 0.2 G0B) — reproducible-from-a-fresh-clone matrix runner.
// Reads matrix.txt (route|role|viewport|mode|interact|name) and runs run.mjs for each line,
// in-process (imports run.mjs's logic would require refactoring it into a function; instead we
// spawn `node run.mjs` per row, same as a fresh clone / CI would). Writes a consolidated
// results/summary.json alongside the existing per-run JSON files.
//
// Run: node .audit-e2e/run-all.mjs [--filter=substring] [--matrix=task10-matrix.txt]
//      [--summary=task10-summary.json]
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const filter = process.argv.find((a) => a.startsWith('--filter='))?.slice('--filter='.length);
const matrixName = process.argv.find((a) => a.startsWith('--matrix='))?.slice('--matrix='.length) || 'matrix.txt';
const summaryName = process.argv.find((a) => a.startsWith('--summary='))?.slice('--summary='.length) || 'summary.json';

if (path.basename(matrixName) !== matrixName || path.basename(summaryName) !== summaryName) {
  throw new Error('--matrix and --summary must be filenames within front/.audit-e2e');
}

const lines = fs
  .readFileSync(path.join(__dirname, matrixName), 'utf8')
  .split('\n')
  .map((l) => l.trim())
  .filter((l) => l && !l.startsWith('#'))
  .filter((l) => !filter || l.includes(filter));

const summary = [];

for (const line of lines) {
  const [route, role, viewport, mode, interact, name] = line.split('|');
  console.log(`\n=== ${name} (${route} ${role} ${viewport} mode=${mode} interact=${interact}) ===`);

  const result = spawnSync(
    process.execPath,
    [
      path.join(__dirname, 'run.mjs'),
      `--route=${route}`,
      `--role=${role}`,
      `--viewport=${viewport}`,
      `--mode=${mode}`,
      `--interact=${interact}`,
      `--name=${name}`
    ],
    { encoding: 'utf8', cwd: path.join(__dirname, '..') }
  );

  let parsed = null;
  try {
    // run.mjs prints one JSON line to stdout.
    const jsonLine = result.stdout.trim().split('\n').pop();
    parsed = JSON.parse(jsonLine);
  } catch {
    console.error(result.stdout, result.stderr);
  }

  summary.push({
    name,
    route,
    role,
    viewport,
    mode,
    interact,
    exitCode: result.status,
    navError: parsed ? parsed.navError : 'PARSE_FAILURE',
    hasOverflow: parsed ? parsed.overflow?.hasOverflow ?? null : null,
    consoleErrors: parsed ? parsed.consoleErrors?.length ?? null : null,
    pageErrors: parsed ? parsed.pageErrors?.length ?? null : null,
    failedRequests: parsed ? parsed.failedRequests?.length ?? null : null,
    interaction: parsed ? parsed.interaction : null
  });
}

fs.mkdirSync(path.join(__dirname, 'results'), { recursive: true });
fs.writeFileSync(path.join(__dirname, 'results', summaryName), JSON.stringify(summary, null, 2));

const clean = summary.filter(
  (s) => s.exitCode === 0 && !s.navError && s.hasOverflow === false && s.consoleErrors === 0 && s.pageErrors === 0 && s.failedRequests === 0
);
console.log(`\n${clean.length}/${summary.length} runs clean (no nav error, no doc overflow, no console/page errors, no failed requests).`);
console.log(`Full results: front/.audit-e2e/results/${summaryName} (per-run JSON/PNG also written there).`);
