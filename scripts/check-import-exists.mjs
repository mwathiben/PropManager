#!/usr/bin/env node
/**
 * Phase-66 BUILD-INTEGRITY-1: scan every TS/Vue/JS file for `@/...`,
 * `/resources/js/...` and relative imports and verify each resolves to
 * a real file on disk. A phantom import (e.g. `@/Layouts/AppLayout.vue`
 * or `@/lang` where neither exists) makes `vite build` abort with a
 * cryptic rollup "Could not load" error that names only the FIRST
 * offender — so a single bad import hides every later one and can sit
 * undetected for many releases. This gate fails fast and lists ALL
 * phantom imports at once, before vite runs.
 *
 * Bare package imports (e.g. `vue`, `@inertiajs/vue3`) are skipped —
 * node_modules resolution is vite's job. Exit 0 when every first-party
 * import resolves; exit 1 with the full offender list otherwise.
 */

import { readFileSync, readdirSync, existsSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');
const jsRoot = join(projectRoot, 'resources', 'js');

// Extensions/index forms a bundler will try for an extensionless import.
const candidates = [
    '', '.ts', '.d.ts', '.tsx', '.js', '.jsx', '.vue', '.mjs', '.json',
    '/index.ts', '/index.d.ts', '/index.js', '/index.vue',
];

const importRegex = /(?:from|import)\s+['"]([^'"]+)['"]|import\(\s*['"]([^'"]+)['"]\s*\)/g;

function* walkFiles(dir) {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const full = join(dir, entry.name);
        if (entry.isDirectory()) {
            if (entry.name === 'node_modules' || entry.name.startsWith('.')) continue;
            yield* walkFiles(full);
        } else if (/\.(ts|tsx|vue|js|jsx|mjs)$/.test(entry.name)) {
            yield full;
        }
    }
}

function resolveBase(spec, fileDir) {
    if (spec.startsWith('@/')) return join(jsRoot, spec.slice(2));
    if (spec.startsWith('/resources/js/')) return join(projectRoot, spec.slice(1));
    if (spec.startsWith('./') || spec.startsWith('../')) return resolve(fileDir, spec);
    return null; // bare package import — vite's concern, not ours
}

function fileExists(base) {
    return candidates.some((ext) => {
        try {
            return existsSync(base + ext) && statSync(base + ext).isFile();
        } catch {
            return false;
        }
    });
}

const violations = [];
let scanned = 0;

for (const file of walkFiles(jsRoot)) {
    const content = readFileSync(file, 'utf8');
    const fileDir = dirname(file);
    let match;

    while ((match = importRegex.exec(content)) !== null) {
        const spec = match[1] ?? match[2];
        if (!spec) continue;
        const base = resolveBase(spec, fileDir);
        if (base === null) continue;
        if (!fileExists(base)) {
            const line = content.slice(0, match.index).split('\n').length;
            violations.push({
                file: file.replace(projectRoot + '\\', '').replace(/\\/g, '/'),
                line,
                spec,
            });
        }
    }
    scanned++;
    importRegex.lastIndex = 0;
}

if (violations.length === 0) {
    console.log(`OK: ${scanned} file(s) scanned, all first-party imports resolve.`);
    process.exit(0);
}

console.error(`FAIL: ${violations.length} phantom import(s) detected:\n`);
for (const v of violations) {
    console.error(`  ${v.file}:${v.line}  ->  ${v.spec}`);
}
console.error('\nThese resolve to no file on disk and will abort `vite build`. Fix or remove them.');
process.exit(1);
