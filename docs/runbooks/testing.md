# Testing & CI hygiene — operator runbook

Phase-69 TEST-DEBT-2 established the test-quality gates below. This is the
single reference for "what fails the test job and why."

## PHPUnit deprecation posture

- **Metadata is attributes-only.** Test metadata uses PHP 8 attributes
  (`#[Test]`, `#[DataProvider]`, `#[Group]`, `#[Depends]`, …), never
  doc-comment annotations (`@test`, `@dataProvider`, `@group`). PHPUnit 11
  deprecated doc-comment metadata; PHPUnit 12 removes it.
- **The runtime gate**: `phpunit.xml` sets `failOnPhpunitDeprecation="true"`
  and `failOnPhpunitWarning="true"`, so any reintroduced doc-comment metadata
  (or other PHPUnit-level deprecation/warning) **fails** the run instead of
  printing a silent WARN line — that silence is how the metadata accumulated.
- **The static guard**: `Phase69MetadataHygieneTest` scans `tests/` for
  doc-comment metadata (in a ` * @annotation` context, so `tenant@test.com`
  literals are not false positives) and fails listing the exact `file:line`.
  It catches offenders even on a toolchain that hasn't surfaced the
  deprecation yet.

### Why not `failOnDeprecation` (PHP/library E_DEPRECATED)?

`failOnPhpunitDeprecation` covers PHPUnit's OWN deprecations (the in-scope
metadata problem). The broader `failOnDeprecation` would also fail on
PHP-runtime / Carbon / Laravel `E_DEPRECATED` notices across the large legacy
suite, which has not been audited clean and cannot be verified locally
(no pcov/xdebug). It is intentionally deferred to a future cycle that
backfills the legacy E_DEPRECATED backlog first.

## Adding test metadata

```php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('idempotency')]
class FooTest extends TestCase
{
    public static function cases(): array { return [...]; }   // providers MUST be static

    #[DataProvider('cases')]
    public function test_bar(string $x): void { ... }
}
```

## Cross-references

- `docs/runbooks/metrics-naming.md` — gauge naming convention + guard
- `docs/runbooks/alert-thresholds.md` — alert catalog
- `.github/workflows/ci.yml` — the CI job order
