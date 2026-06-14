---
name: pr-review-harness
description: MANDATORY review harness for every substantive (non-docs) change before merge — run the deterministic checks AND the review-agent panel, triage, fix, re-verify. Invoke before committing/merging any PR that touches app/, database/, routes/, or resources/js.
---

# PR Review Harness

The gate to merge a substantive PR is **tests green AND review addressed** — never CI alone.
Building on unreviewed code is how the house falls in. Run this harness on the diff before
every merge of a non-docs change.

## When it applies

| Change | Harness |
|---|---|
| Touches `app/`, `database/`, `routes/`, `resources/js/`, `bootstrap/` | **Full harness (required)** |
| Money / payments / fees / payouts / tenancy / auth / consent / agreements | Full harness **+ every panel agent** |
| Docs-only (`docs/`, `*.md`) | Skip the panel; Pint/tests only if code is touched |

## Step 1 — Deterministic checks (run first, fix before spawning agents)

```
php vendor/bin/pint --test <changed paths>
php vendor/bin/phpmd <changed app paths> text phpmd.xml   # 2>/dev/null | grep -v 'Deprecated:'
php artisan test --compact <affected test files/dirs>      # the RED→GREEN you wrote
```

## Step 2 — Review panel (spawn in parallel, report-only)

Give each agent: the **commit range / file list**, the **highest-risk concern** (almost always
multi-tenant `TenantScope` / `landlord_id`, then money-correctness), and ask for findings as
`SEVERITY (CRITICAL/WARNING/SUGGESTION) · file:line · issue · fix`. Tell them **report only,
do not modify code**.

| Agent | Always? | Focus |
|---|---|---|
| `coderabbit:code-reviewer` | yes | bugs, security, correctness |
| `pr-review-toolkit:code-reviewer` | yes | adherence to CLAUDE.md conventions (FormRequests, enum-vs-string, TenantScope, DB::transaction, secret-safe logging, return types) |
| `pr-review-toolkit:silent-failure-hunter` | yes | swallowed errors, `match`/default arms that mask bad input, null/fallback that hides a real problem |
| `pr-review-toolkit:type-design-analyzer` | when new types/enums/VOs are added | encapsulation, invariants, make illegal states unrepresentable |

## Step 3 — Triage & fix

**Every flagged issue gets SOLVED — there is no "note it and move on."** Pre-existing, "it's
the convention," and "out of scope" are NOT exemptions. This is a hard, non-negotiable project
rule (zero tolerance for known issues): a problem is a problem regardless of how often it appears.

- **CRITICAL / WARNING → fix before merge.** Money, tenancy isolation, auth, consent integrity,
  and legal-correctness findings are always fix-now regardless of label.
- **SUGGESTION → also solve it.** Fix in this PR if related/cheap; otherwise open an **immediate
  dedicated follow-up PR** (or a `spawn_task` you actually execute). Never just "log" and drop it.
- **Pre-existing / out-of-scope is NOT an exemption.** A flagged flaw is solved regardless of who
  introduced it or when — same two options (this PR, or an immediate follow-up PR). Do **not**
  perpetuate a bad pattern because it is the existing convention; fix it where you touch it.
- Re-run Step 1 after fixing. For a material fix, re-run the relevant panel agent.

## Step 4 — Merge

Only when Step 1 is green **and** every CRITICAL/WARNING is fixed or explicitly, defensibly
deferred. Then the CI watcher merges on full-suite green (the inherited RTL Visual Snapshots
failure is non-required — merge past it).

## Anti-patterns

- Merging on "Tests pass" while the CodeRabbit GitHub review is still running / unread.
- Treating a SUGGESTION as auto-ignorable. Decide and record.
- Spawning the panel but not actually applying its findings (review theatre).
