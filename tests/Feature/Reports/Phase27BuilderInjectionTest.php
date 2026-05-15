<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Services\Reports\ReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase-27 BI-BUILDER-3 security regression: SQL injection payloads
 * fired at every input slot of ReportBuilderService::run() must all
 * be rejected at the validation entrance.
 *
 * This is the gate for the entire custom-report-builder feature.
 * If any single assertion in this file ever expectedly passes (i.e.
 * an injection payload gets accepted), the builder is INSECURE and
 * the merge must be blocked. The Phase-1 SQLi cleanup (commit
 * 03e17b7) is the standard this test maintains.
 *
 * Adding a new field to ReportBuilderService::ALLOWED_FIELDS
 * automatically inherits the gate — no new test needed, the
 * test_field_allowlist_is_locked assertion adapts.
 */
class Phase27BuilderInjectionTest extends TestCase
{
    use RefreshDatabase;

    private ReportBuilderService $service;

    private int $landlordId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportBuilderService::class);
        $this->landlordId = User::factory()->create(['role' => 'landlord'])->id;
    }

    /**
     * @return list<string>
     */
    public static function injectionPayloads(): array
    {
        return [
            "' OR 1=1 --",
            '"; DROP TABLE users; --',
            "'; SELECT * FROM users WHERE '1'='1",
            "1' UNION SELECT password FROM users --",
            '1; DELETE FROM payments; --',
            '1 OR 1=1',
            "admin'--",
            "admin' #",
            "' OR 'x'='x",
            "1' AND SLEEP(5)--",
            "' || (SELECT password FROM users LIMIT 1) || '",
            "%' OR 1=1 --",
            'payments`; DROP TABLE--',
            'payments WHERE 1=1; --',
            '../../etc/passwd',
            '<script>alert(1)</script>',
            "'; EXEC xp_cmdshell--",
            '0 OR 1=1',
            "1' AND (SELECT COUNT(*) FROM users) > 0 --",
            'id IN (SELECT id FROM users)',
        ];
    }

    public function test_table_must_be_in_allowlist(): void
    {
        foreach (self::injectionPayloads() as $payload) {
            $this->expectInvalid([
                'table' => $payload,
                'fields' => ['payment.amount'],
            ], 'table', $payload);
        }
    }

    public function test_table_rejects_unlisted_real_tables(): void
    {
        // Real tables that exist but are NOT in ALLOWED_TABLES must
        // still reject — the gate is positive (only these), not
        // negative (not those).
        foreach (['users', 'security_logs', 'audit_logs', 'webhook_subscriptions'] as $forbidden) {
            $this->expectInvalid([
                'table' => $forbidden,
                'fields' => ['payment.amount'],
            ], 'table', $forbidden);
        }
    }

    public function test_field_must_be_in_allowlist(): void
    {
        foreach (self::injectionPayloads() as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => [$payload],
            ], 'fields', $payload);
        }
    }

    public function test_field_rejects_real_columns_not_on_allowlist(): void
    {
        foreach (['payments.id', 'users.password', 'leases.deleted_at'] as $forbidden) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => [$forbidden],
            ], 'fields', $forbidden);
        }
    }

    public function test_filter_field_must_be_in_allowlist(): void
    {
        foreach (self::injectionPayloads() as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'filters' => [['field' => $payload, 'op' => '=', 'value' => 1]],
            ], 'filters.0.field', $payload);
        }
    }

    public function test_filter_operator_must_be_in_allowlist(): void
    {
        $payloads = array_merge(self::injectionPayloads(), [
            'OR',
            'UNION',
            ';',
            'LIKE',
            'REGEXP',
            'BETWEEN',
            '=;DROP',
        ]);
        foreach ($payloads as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'filters' => [['field' => 'payment.amount', 'op' => $payload, 'value' => 1]],
            ], 'filters.0.op', $payload);
        }
    }

    public function test_string_field_rejects_oversized_value(): void
    {
        // A 1MB string is rejected outright — the allowlist enforces
        // <= 100 chars on string filters.
        $this->expectInvalid([
            'table' => 'payments',
            'fields' => ['payment.amount'],
            'filters' => [['field' => 'payment.payment_method', 'op' => '=', 'value' => str_repeat('a', 101)]],
        ], 'filters.0.value', 'oversized string');
    }

    public function test_numeric_field_rejects_non_numeric_value(): void
    {
        foreach (["'; DROP", '[]', 'true', 'NaN', 'undefined'] as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'filters' => [['field' => 'payment.amount', 'op' => '=', 'value' => $payload]],
            ], 'filters.0.value', $payload);
        }
    }

    public function test_date_field_rejects_non_iso_value(): void
    {
        foreach (['yesterday', "2026-01-01'; DROP--", '01/01/2026', '01-01-2026', "' OR 1=1 --"] as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'filters' => [['field' => 'payment.payment_date', 'op' => '=', 'value' => $payload]],
            ], 'filters.0.value', $payload);
        }
    }

    public function test_group_by_must_be_in_allowlist(): void
    {
        foreach (self::injectionPayloads() as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'group_by' => [$payload],
            ], 'group_by.0', $payload);
        }
    }

    public function test_sort_field_must_be_in_allowlist(): void
    {
        foreach (self::injectionPayloads() as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'sort_by' => [['field' => $payload, 'direction' => 'asc']],
            ], 'sort_by.0.field', $payload);
        }
    }

    public function test_sort_direction_must_be_asc_or_desc(): void
    {
        foreach (['asc; DROP', '1=1', 'ASC--', 'random()'] as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'sort_by' => [['field' => 'payment.amount', 'direction' => $payload]],
            ], 'sort_by.0.direction', $payload);
        }
    }

    public function test_limit_must_be_integer_within_range(): void
    {
        foreach ([0, -1, 10001, '100; DROP', '100', 100.5, null, []] as $payload) {
            $this->expectInvalid([
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'limit' => $payload,
            ], 'limit', $payload);
        }
    }

    public function test_valid_config_executes_without_throwing(): void
    {
        // Sanity check: a config that uses only allowlisted values
        // runs successfully. If this test fails the allowlist is
        // too restrictive.
        $rows = $this->service->run([
            'table' => 'payments',
            'fields' => ['payment.amount', 'payment.payment_date'],
            'filters' => [
                ['field' => 'payment.amount', 'op' => '>=', 'value' => 0],
                ['field' => 'payment.payment_method', 'op' => 'in', 'value' => ['cash', 'mobile_money']],
            ],
            'sort_by' => [['field' => 'payment.payment_date', 'direction' => 'desc']],
            'limit' => 50,
        ], $this->landlordId);

        $this->assertIsArray($rows);
    }

    public function test_field_allowlist_is_locked(): void
    {
        // BI-BUILDER-3 contract: every entry in ALLOWED_FIELDS must
        // reference a real ALLOWED_TABLES entry. If a contributor adds
        // a field with a typo'd table, this test catches it before
        // any query runs.
        foreach (ReportBuilderService::ALLOWED_FIELDS as $key => $meta) {
            $this->assertContains(
                $meta['table'],
                ReportBuilderService::ALLOWED_TABLES,
                "BI-BUILDER-3: ALLOWED_FIELDS[{$key}].table must be in ALLOWED_TABLES.",
            );
            $this->assertContains(
                $meta['type'],
                ['numeric', 'date', 'string', 'boolean'],
                "BI-BUILDER-3: ALLOWED_FIELDS[{$key}].type must be a known type.",
            );
        }
    }

    private function expectInvalid(array $config, string $expectedKey, mixed $payload): void
    {
        try {
            $this->service->run($config, $this->landlordId);
            $payloadStr = is_string($payload) ? $payload : json_encode($payload);
            $this->fail("BI-BUILDER-3 INJECTION REGRESSION: config with payload '{$payloadStr}' on '{$expectedKey}' was accepted — must reject.");
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey(
                $expectedKey,
                $errors,
                "BI-BUILDER-3: ValidationException for payload '".(is_string($payload) ? $payload : json_encode($payload))."' must surface error on '{$expectedKey}'. Got: ".json_encode($errors),
            );
        }
    }
}
