#!/usr/bin/env node
/**
 * Phase-38 DEFER-CASE-SENSITIVITY-2: scan every TS/Vue file for
 * `from '@/...'` imports and validate each path segment matches the
 * actual filesystem case. Windows is case-insensitive so wrong-case
 * imports compile locally but fail on Linux production. Run as a
 * pre-build gate.
 *
 * Exit 0 when all imports resolve case-exactly. Exit 1 with a list
 * of mismatched imports otherwise (one per line, file:line: from
 * '@/Wrong/Path' should be '@/wrong/path').
 */

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');
const jsRoot = join(projectRoot, 'resources', 'js');

const importRegex = /from\s+['"]@\/([^'"]+)['"]/g;

/**
 * Recursively walk a directory and yield absolute file paths matching
 * the .ts/.tsx/.vue/.js extension allowlist.
 */
function* walkFiles(dir) {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const full = join(dir, entry.name);
        if (entry.isDirectory()) {
            if (entry.name === 'node_modules' || entry.name.startsWith('.')) continue;
            yield* walkFiles(full);
        } else if (/\.(ts|tsx|vue|js|jsx)$/.test(entry.name)) {
            yield full;
        }
    }
}

/**
 * Validate only the DIRECTORY segments of an @/-prefixed import.
 * The last segment (file basename) is intentionally skipped because
 * bundlers accept .ts/.vue/.d.ts/etc. extensions interchangeably and
 * the original DEFER-CASE-SENSITIVITY-1 bug was directory-case
 * (`@/Composables/useHelpDrawer` while dir is `composables/`) — not
 * file-case. Returns { ok, mismatchSegment, actualCase } describing
 * the first mismatched directory.
 */
function resolveCaseExact(importPath) {
    const parts = importPath.split('/').filter(Boolean);
    let cursor = jsRoot;

    // Only check directory parts (skip the last/filename segment).
    for (let i = 0; i < parts.length - 1; i++) {
        const want = parts[i];
        let entries;
        try {
            entries = readdirSync(cursor);
        } catch {
            return { ok: true }; // can't traverse — let the bundler decide
        }

        const exactMatch = entries.find((e) => e === want);
        if (exactMatch) {
            cursor = join(cursor, exactMatch);
            continue;
        }

        const ciMatch = entries.find((e) => e.toLowerCase() === want.toLowerCase());
        if (ciMatch) {
            const rebuilt = parts.slice(0, i).concat([ciMatch]).concat(parts.slice(i + 1)).join('/');
            return { ok: false, actual: rebuilt };
        }

        return { ok: true }; // directory doesn't exist — let bundler error
    }
    return { ok: true };
}

const violations = [];
let scanned = 0;

for (const file of walkFiles(jsRoot)) {
    const content = readFileSync(file, 'utf8');
    let match;
    let lineNum = 1;
    let lastIndex = 0;

    while ((match = importRegex.exec(content)) !== null) {
        const importPath = match[1];
        // Count newlines from start to this match for line number.
        const upto = content.slice(lastIndex, match.index);
        lineNum += (upto.match(/\n/g) || []).length;
        lastIndex = match.index;

        const resolution = resolveCaseExact(importPath);
        if (resolution === null) {
            // Import doesn't resolve at all — could be a TS path alias,
            // a barrel re-export, etc. Skip rather than flag.
            continue;
        }
        if (!resolution.ok) {
            violations.push({
                file: file.replace(projectRoot + '/', '').replace(/\\/g, '/'),
                line: lineNum,
                imported: `@/${importPath}`,
                actual: `@/${resolution.actual}`,
            });
        }
    }
    scanned++;
    importRegex.lastIndex = 0;
}

if (violations.length === 0) {
    console.log(`OK: ${scanned} file(s) scanned, all @/ imports match filesystem case.`);
    process.exit(0);
}

console.error(`FAIL: ${violations.length} case-mismatched import(s) detected:\n`);
for (const v of violations) {
    console.error(`  ${v.file}:${v.line}`);
    console.error(`    imported: ${v.imported}`);
    console.error(`    should be: ${v.actual}\n`);
}
console.error('Windows resolves these case-insensitively but Linux production builds will fail.');
process.exit(1);
