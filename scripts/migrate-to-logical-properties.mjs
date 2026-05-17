#!/usr/bin/env node
/**
 * Phase-43 RTL-PREP-2: codemod that swaps Tailwind LTR-only
 * directional utility classes for their logical equivalents.
 * Targets .vue, .tsx, .ts, .jsx, .js, and .css files under
 * resources/.
 *
 * Phase 43 ships the script — Phase 44 [I18N-RTL] runs it
 * en-masse. By itself the script is idempotent: running it
 * twice produces the same output as running it once.
 *
 * Usage:
 *   node scripts/migrate-to-logical-properties.mjs --dry-run
 *   node scripts/migrate-to-logical-properties.mjs --apply
 *
 * Substitution table (preserves Tailwind variant prefixes like
 * `hover:`, `sm:`, `dark:`, `focus:`, etc.):
 *
 *   ml-X       -> ms-X       (margin-inline-start)
 *   mr-X       -> me-X       (margin-inline-end)
 *   pl-X       -> ps-X       (padding-inline-start)
 *   pr-X       -> pe-X       (padding-inline-end)
 *   left-X     -> start-X
 *   right-X    -> end-X
 *   border-l-X -> border-s-X
 *   border-r-X -> border-e-X
 *   rounded-l-X        -> rounded-s-X
 *   rounded-r-X        -> rounded-e-X
 *   rounded-tl-X       -> rounded-ss-X
 *   rounded-tr-X       -> rounded-se-X
 *   rounded-bl-X       -> rounded-es-X
 *   rounded-br-X       -> rounded-ee-X
 *   text-left          -> text-start
 *   text-right         -> text-end
 */

import { promises as fs } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const PROJECT_ROOT = path.resolve(new URL('.', import.meta.url).pathname, '..');
const SOURCE_ROOT = path.join(PROJECT_ROOT, 'resources');

const SUBSTITUTIONS = [
  // The `(?<![\w-])` lookbehind prevents matching the middle of a
  // longer class (e.g. `ml-2` is replaced, but `html-2` isn't).
  // The trailing `(?=\b)` keeps Tailwind suffixes like `-px` working.
  [/(?<![\w-])ml-(?=[\w[/-])/g, 'ms-'],
  [/(?<![\w-])mr-(?=[\w[/-])/g, 'me-'],
  [/(?<![\w-])pl-(?=[\w[/-])/g, 'ps-'],
  [/(?<![\w-])pr-(?=[\w[/-])/g, 'pe-'],
  [/(?<![\w-])border-l-(?=[\w[/-])/g, 'border-s-'],
  [/(?<![\w-])border-r-(?=[\w[/-])/g, 'border-e-'],
  [/(?<![\w-])rounded-tl-(?=[\w[/-])/g, 'rounded-ss-'],
  [/(?<![\w-])rounded-tr-(?=[\w[/-])/g, 'rounded-se-'],
  [/(?<![\w-])rounded-bl-(?=[\w[/-])/g, 'rounded-es-'],
  [/(?<![\w-])rounded-br-(?=[\w[/-])/g, 'rounded-ee-'],
  [/(?<![\w-])rounded-l-(?=[\w[/-])/g, 'rounded-s-'],
  [/(?<![\w-])rounded-r-(?=[\w[/-])/g, 'rounded-e-'],
  [/(?<![\w-])text-left(?![\w-])/g, 'text-start'],
  [/(?<![\w-])text-right(?![\w-])/g, 'text-end'],
  [/(?<![\w-])left-(?=[\w[/-])/g, 'start-'],
  [/(?<![\w-])right-(?=[\w[/-])/g, 'end-'],
];

const EXTENSIONS = new Set(['.vue', '.tsx', '.ts', '.jsx', '.js', '.css']);

async function walk(dir) {
  const entries = await fs.readdir(dir, { withFileTypes: true });
  const out = [];
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...(await walk(full)));
    } else if (EXTENSIONS.has(path.extname(entry.name))) {
      out.push(full);
    }
  }
  return out;
}

export function transform(input) {
  let out = input;
  for (const [pattern, replacement] of SUBSTITUTIONS) {
    out = out.replace(pattern, replacement);
  }
  return out;
}

async function main() {
  const args = process.argv.slice(2);
  const dryRun = args.includes('--dry-run');
  const apply = args.includes('--apply');

  if (!dryRun && !apply) {
    console.error('Usage: node scripts/migrate-to-logical-properties.mjs [--dry-run|--apply]');
    process.exit(2);
  }

  const files = await walk(SOURCE_ROOT);
  let changed = 0;

  for (const file of files) {
    const input = await fs.readFile(file, 'utf8');
    const output = transform(input);
    if (output === input) continue;
    changed++;
    const rel = path.relative(PROJECT_ROOT, file);
    if (dryRun) {
      console.log(`would-change  ${rel}`);
    } else {
      await fs.writeFile(file, output, 'utf8');
      console.log(`changed       ${rel}`);
    }
  }

  console.log(`${dryRun ? 'would change' : 'changed'} ${changed} of ${files.length} files`);
}

// Only run main() when invoked directly. Lets tests import {transform}.
const entryPath = path.resolve(process.argv[1] ?? '');
const thisPath = path.resolve(new URL(import.meta.url).pathname);
if (entryPath === thisPath) {
  main().catch((err) => {
    console.error(err);
    process.exit(1);
  });
}
