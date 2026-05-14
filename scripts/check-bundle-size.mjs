// Phase-22 PERF-CI-3: frontend bundle-size budget gate.
//
// Runs after `npm run build` in the CI build job. Kenyan network
// reality (3G common, 2G rural) makes JS payload a real user-facing
// latency factor — a careless dependency import that doubles the
// vendor chunk should fail CI, not ship silently.
//
// Budgets are set from the CURRENT measured build with generous
// headroom: the gate catches a ~regression (a big jump), not normal
// incremental growth. Raw (uncompressed) bytes are used — deterministic
// and simple; gzip ratios are fairly stable so raw is a fine proxy.
//
// To re-baseline after intentional growth: rebuild, read the printed
// totals, bump the budgets below with headroom, and say why in the
// commit message.

import { readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ASSETS_DIR = 'public/build/assets';

// Current build (2026-05-14): ~3.2 MB total JS, largest chunk
// vue-core ~205 KB. Budgets leave headroom for normal growth.
const TOTAL_JS_BUDGET_BYTES = 4_500_000; // ~4.5 MB
const LARGEST_CHUNK_BUDGET_BYTES = 350_000; // ~350 KB

const kb = (b) => (b / 1000).toFixed(1);
const mb = (b) => (b / 1_000_000).toFixed(2);

let jsFiles;
try {
    jsFiles = readdirSync(ASSETS_DIR).filter((f) => f.endsWith('.js'));
} catch {
    console.error(`check-bundle-size: ${ASSETS_DIR} not found — did \`npm run build\` run first?`);
    process.exit(1);
}

if (jsFiles.length === 0) {
    console.error('check-bundle-size: no JS assets found in the build output.');
    process.exit(1);
}

let totalBytes = 0;
let largest = { name: '', size: 0 };

for (const file of jsFiles) {
    const size = statSync(join(ASSETS_DIR, file)).size;
    totalBytes += size;
    if (size > largest.size) {
        largest = { name: file, size };
    }
}

console.log(`Bundle-size budget check (${jsFiles.length} JS chunks):`);
console.log(`  Total JS:      ${mb(totalBytes)} MB  (budget ${mb(TOTAL_JS_BUDGET_BYTES)} MB)`);
console.log(`  Largest chunk: ${largest.name} ${kb(largest.size)} KB  (budget ${kb(LARGEST_CHUNK_BUDGET_BYTES)} KB)`);

let failed = false;

if (totalBytes > TOTAL_JS_BUDGET_BYTES) {
    console.error(`  FAIL: total JS ${mb(totalBytes)} MB exceeds the ${mb(TOTAL_JS_BUDGET_BYTES)} MB budget.`);
    failed = true;
}

if (largest.size > LARGEST_CHUNK_BUDGET_BYTES) {
    console.error(`  FAIL: chunk ${largest.name} (${kb(largest.size)} KB) exceeds the ${kb(LARGEST_CHUNK_BUDGET_BYTES)} KB single-chunk budget.`);
    failed = true;
}

if (failed) {
    console.error('check-bundle-size: bundle is over budget. Investigate the regression or re-baseline (see the script header).');
    process.exit(1);
}

console.log('  OK: bundle is within budget.');
process.exit(0);
