#!/usr/bin/env node
/**
 * Phase-53 ESLINT-RATCHET-3: shrink-only baseline watchdog.
 *
 * Runs ESLint in JSON-formatter mode, counts violations per rule,
 * compares each count to .eslint-baseline.json, exits non-zero when
 * any current count exceeds its baseline. Output is one line per
 * tracked rule with PASS / FAIL + count delta.
 *
 * To fix a real violation: ship the fix, then re-run this script
 * and lower the baseline entry to match. Never raise the baseline.
 *
 * Usage: node scripts/lint-baseline.mjs [target-path]
 */
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const target = process.argv[2] || 'resources/js';
const baselinePath = resolve(process.cwd(), '.eslint-baseline.json');

let baseline;
try {
    baseline = JSON.parse(readFileSync(baselinePath, 'utf-8'));
} catch (err) {
    console.error(`Failed to read baseline at ${baselinePath}: ${err.message}`);
    process.exit(1);
}

const tracked = baseline.rules || {};
if (Object.keys(tracked).length === 0) {
    console.error('No tracked rules in .eslint-baseline.json — nothing to gate.');
    process.exit(1);
}

// ESLint exits 1 when there are lint errors, which is the common
// case here. Write JSON to a temp file to dodge the stdout pipe
// buffer cap that bites on large reports (3700+ messages).
const tempDir = mkdtempSync(join(tmpdir(), 'eslint-baseline-'));
const outFile = join(tempDir, 'report.json');

const result = spawnSync(
    process.platform === 'win32' ? 'npx.cmd' : 'npx',
    ['eslint', target, '--format', 'json', '--output-file', outFile],
    { encoding: 'utf-8', stdio: ['ignore', 'pipe', 'inherit'], shell: process.platform === 'win32' },
);
// Exit code 0 = no issues, 1 = lint issues found (still produces JSON),
// 2 = ESLint config/runtime error → bail.
if (result.error) {
    console.error('Spawn error:', result.error.message);
    rmSync(tempDir, { recursive: true, force: true });
    process.exit(1);
}
if (result.status !== 0 && result.status !== 1) {
    console.error('ESLint failed to run; exit code', result.status);
    rmSync(tempDir, { recursive: true, force: true });
    process.exit(1);
}

let report;
try {
    const raw = readFileSync(outFile, 'utf-8');
    report = JSON.parse(raw);
} catch (err) {
    console.error('Failed to read/parse ESLint JSON output:', err.message);
    rmSync(tempDir, { recursive: true, force: true });
    process.exit(1);
}
rmSync(tempDir, { recursive: true, force: true });

const counts = {};
for (const file of report) {
    for (const msg of file.messages || []) {
        const ruleId = msg.ruleId;
        if (!ruleId || !(ruleId in tracked)) continue;
        counts[ruleId] = (counts[ruleId] || 0) + 1;
    }
}

let failed = false;
for (const ruleId of Object.keys(tracked)) {
    const allowed = tracked[ruleId];
    const actual = counts[ruleId] || 0;
    const delta = actual - allowed;
    const status = delta > 0 ? 'FAIL' : 'PASS';
    const sign = delta > 0 ? '+' : '';
    console.log(`[${status}] ${ruleId}: ${actual} / baseline ${allowed} (${sign}${delta})`);
    if (delta > 0) failed = true;
}

if (failed) {
    console.error('');
    console.error('Lint baseline exceeded. Fix the new violations OR shrink the baseline');
    console.error('only if you have eliminated previously-counted ones.');
    process.exit(1);
}

console.log('');
console.log('lint baseline holds.');
