#!/usr/bin/env node
/**
 * Phase-79 NAV-REACH-1: orphan-page detector.
 *
 * Rebuild of the earlier C:/tmp/page-audit.mjs. A page can ship routable AND
 * renderable yet be unreachable because nothing links to it (the 2026-05-21
 * "can't navigate to the new hubs" report). InertiaPageReachabilityTest only
 * proves a page RENDERS; this proves it is LINKED.
 *
 * It reads `php artisan route:list --json`, keeps named GET routes whose
 * controller method renders an Inertia page, and flags any whose route name is
 * never referenced via route('<name>') anywhere under resources/js — minus an
 * allowlist of pages that are intentionally reached without an in-app link
 * (auth, signed/email links, redirect targets, programmatic redirects).
 *
 * Exit 0 = no orphans (beyond the allowlist); exit 1 = orphans found.
 * The PHP guard (Phase79NavReachabilityTest) enforces the same in CI; this is
 * the developer-facing sweep with a readable report.
 */
import { execSync } from 'node:child_process';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

const ROOT = process.cwd();
const JS_DIR = join(ROOT, 'resources', 'js');

// Pages reached without an in-app route() link, by design.
const ALLOWLIST = new Set([
    // Auth / guest flows (Breeze) — reached via guest links or direct URLs.
    'login', 'register', 'password.request', 'password.reset', 'password.confirm',
    'verification.notice', 'two-factor.login',
    // Programmatic redirect targets / wizards entered via redirect.
    'onboarding.index', 'onboarding.step', 'onboarding.create',
    // Signed / email-linked entry points.
    'invitations.accept', 'tenant-invitations.accept',
    // Phase-95: water-client invite deep-link (reached via the invitation email).
    'water-invite.show',
]);

// Phase-79 NAV-REACH-1: shrink-only baseline of pre-existing pages reached via
// a hub tab (?tab=), a middleware redirect, the separate vendor portal, a
// settings sub-nav, or an external/API entry — not a direct route() link. New
// orphans (a page that is neither linked nor here) fail the audit. Wiring an
// entry then removing it from the baseline is the intended ratchet direction.
const BASELINE = new Set(JSON.parse(readFileSync(join(ROOT, 'scripts', 'nav-audit-baseline.json'), 'utf8')));

function collectRouteRefs(dir) {
    const refs = new Set();
    const re = /route\(\s*['"]([A-Za-z0-9_.-]+)['"]/g;
    const walk = (d) => {
        for (const entry of readdirSync(d)) {
            const p = join(d, entry);
            const s = statSync(p);
            if (s.isDirectory()) {
                walk(p);
            } else if (['.vue', '.ts', '.js'].includes(extname(p))) {
                const src = readFileSync(p, 'utf8');
                let m;
                while ((m = re.exec(src)) !== null) {
                    refs.add(m[1]);
                }
            }
        }
    };
    walk(dir);
    return refs;
}

function inertiaTarget(action) {
    // action = "App\\Http\\Controllers\\Foo@bar"
    if (!action.includes('@')) return null;
    const [cls, method] = action.split('@');
    const rel = cls.replace(/^App\\/, 'app/').replace(/\\/g, '/') + '.php';
    let src;
    try {
        src = readFileSync(join(ROOT, rel), 'utf8');
    } catch {
        return null;
    }
    // crude: does the method body call Inertia::render / ->render('Page')?
    const mIdx = src.indexOf(`function ${method}(`);
    if (mIdx === -1) return null;
    const slice = src.slice(mIdx, mIdx + 4000);
    return /(?:Inertia::render|->render)\(\s*['"][A-Za-z0-9_/.-]+['"]/.test(slice);
}

const routes = JSON.parse(execSync('php artisan route:list --json', { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 }));
const refs = collectRouteRefs(JS_DIR);

const newOrphans = [];
const seenBaseline = new Set();
for (const r of routes) {
    if (!r.name) continue;
    if (!r.method.split('|').includes('GET')) continue;
    if (ALLOWLIST.has(r.name)) continue;
    if (!inertiaTarget(r.action)) continue;
    if (refs.has(r.name)) continue;
    if (BASELINE.has(r.name)) {
        seenBaseline.add(r.name);
        continue;
    }
    newOrphans.push(`${r.name}  (${r.uri})`);
}

if (newOrphans.length > 0) {
    console.error(`nav-audit: ${newOrphans.length} NEW orphaned Inertia page(s) — routable + renderable but never linked:`);
    for (const o of newOrphans.sort()) console.error('  - ' + o);
    console.error('\nWire a route() link in the nav or a parent page, or add to the ALLOWLIST/baseline if intentionally direct.');
    process.exit(1);
}

const stale = [...BASELINE].filter((n) => !seenBaseline.has(n)).sort();
if (stale.length > 0) {
    console.error(`nav-audit: ${stale.length} baseline entr(y/ies) now linked — remove from scripts/nav-audit-baseline.json (shrink-only):`);
    for (const s of stale) console.error('  - ' + s);
    process.exit(1);
}

console.log(`nav-audit: clean. No new orphans; ${seenBaseline.size} known reached-otherwise pages baselined.`);
