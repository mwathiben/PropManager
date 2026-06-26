<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ReportMetric;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase-27 BI-BUILDER-3: SAFE SQL generator for the custom report builder.
 *
 * Security contract (enforced by Phase27BuilderInjectionTest):
 *   1. Every field/table/operator/group-by key is validated against the
 *      in-file allowlists below; anything unknown throws before the query starts.
 *   2. Every user-supplied value is bound via ->where() / ->whereIn() — no
 *      DB::raw() with request input anywhere in this file.
 *
 * Allowlist extension: add field → ALLOWED_FIELDS, table → ALLOWED_TABLES,
 * join shape → JOINS, then update docs/runbooks/bi.md.
 */
class ReportBuilderService
{
    public const ALLOWED_TABLES = ['payments', 'invoices', 'leases'];

    /**
     * field_key => [table, column, type, label]. type drives operator restrictions.
     * Phase-73 METRICS-DEPTH-1 extended this list; MetricFormulaService reads it automatically.
     */
    public const ALLOWED_FIELDS = [
        'payment.amount' => ['table' => 'payments', 'column' => 'amount',                 'type' => 'numeric', 'label' => 'Payment amount'],
        'payment.payment_date' => ['table' => 'payments', 'column' => 'payment_date',           'type' => 'date',    'label' => 'Payment date'],
        'payment.payment_method' => ['table' => 'payments', 'column' => 'payment_method',         'type' => 'string',  'label' => 'Payment method'],
        'payment.reconciliation_status' => ['table' => 'payments', 'column' => 'reconciliation_status',  'type' => 'string',  'label' => 'Payment reconciliation status'],
        'invoice.total_due' => ['table' => 'invoices', 'column' => 'total_due',              'type' => 'numeric', 'label' => 'Invoice total'],
        'invoice.amount_paid' => ['table' => 'invoices', 'column' => 'amount_paid',            'type' => 'numeric', 'label' => 'Invoice amount paid'],
        'invoice.status' => ['table' => 'invoices', 'column' => 'status',                 'type' => 'string',  'label' => 'Invoice status'],
        'invoice.due_date' => ['table' => 'invoices', 'column' => 'due_date',               'type' => 'date',    'label' => 'Invoice due date'],
        'invoice.rent_due' => ['table' => 'invoices', 'column' => 'rent_due',               'type' => 'numeric', 'label' => 'Invoice rent due'],
        'invoice.arrears' => ['table' => 'invoices', 'column' => 'arrears',                'type' => 'numeric', 'label' => 'Invoice arrears'],
        'invoice.late_fees_total' => ['table' => 'invoices', 'column' => 'late_fees_total',        'type' => 'numeric', 'label' => 'Invoice late fees'],
        'invoice.billing_period_start' => ['table' => 'invoices', 'column' => 'billing_period_start',   'type' => 'date',    'label' => 'Invoice billing period start'],
        'lease.rent_amount' => ['table' => 'leases',   'column' => 'rent_amount',            'type' => 'numeric', 'label' => 'Lease rent amount'],
        'lease.start_date' => ['table' => 'leases',   'column' => 'start_date',             'type' => 'date',    'label' => 'Lease start date'],
        'lease.end_date' => ['table' => 'leases',   'column' => 'end_date',               'type' => 'date',    'label' => 'Lease end date'],
        'lease.is_active' => ['table' => 'leases',   'column' => 'is_active',              'type' => 'boolean', 'label' => 'Lease active'],
        'lease.deposit_amount' => ['table' => 'leases',   'column' => 'deposit_amount',         'type' => 'numeric', 'label' => 'Lease deposit amount'],
        'lease.service_charge' => ['table' => 'leases',   'column' => 'service_charge',         'type' => 'numeric', 'label' => 'Lease service charge'],
    ];

    public const NUMERIC_OPERATORS = ['=', '!=', '<', '<=', '>', '>='];

    public const DATE_OPERATORS = ['=', '!=', '<', '<=', '>', '>='];

    public const STRING_OPERATORS = ['=', '!=', 'in', 'not_in'];

    public const BOOLEAN_OPERATORS = ['=', '!='];

    public const SORT_DIRECTIONS = ['asc', 'desc'];

    /** Pre-defined join shapes keyed by root_table to joined_table. */
    private const JOINS = [
        'payments' => [
            'leases' => ['payments.lease_id', '=', 'leases.id'],
            'invoices' => ['payments.invoice_id', '=', 'invoices.id'],
        ],
        'invoices' => [
            'leases' => ['invoices.lease_id', '=', 'leases.id'],
        ],
    ];

    public function __construct(
        protected ?MetricFormulaService $metricFormulas = null,
    ) {
        $this->metricFormulas ??= new MetricFormulaService;
    }

    public function run(array $config, int $landlordId): array
    {
        $table = $this->requireTable($config['table'] ?? null);
        $fields = $this->requireFields((array) ($config['fields'] ?? []));
        $filters = $this->validateFilters((array) ($config['filters'] ?? []));
        $groupBy = $this->validateGroupBy((array) ($config['group_by'] ?? []));
        $sortBy = $this->validateSortBy((array) ($config['sort_by'] ?? []));
        $limit = $this->validateLimit(array_key_exists('limit', $config) ? $config['limit'] : 200);
        $customMetrics = $this->validateCustomMetrics((array) ($config['custom_metrics'] ?? []), $landlordId);

        $query = DB::table($table);

        $this->applyRequiredJoins($query, $table, $fields);

        // CRITICAL: landlord scoping — no escape hatch (asserted by Phase27BuilderInjectionTest).
        $query->where("{$table}.landlord_id", '=', $landlordId);

        $this->applyFiltersToQuery($query, $filters);

        $selects = array_map(function (string $field) {
            $meta = self::ALLOWED_FIELDS[$field];

            return "{$meta['table']}.{$meta['column']} as ".str_replace('.', '_', $field);
        }, $fields);

        $query->select($selects);

        foreach ($groupBy as $field) {
            $meta = self::ALLOWED_FIELDS[$field];
            $query->groupBy("{$meta['table']}.{$meta['column']}");
        }

        foreach ($sortBy as $sort) {
            $meta = self::ALLOWED_FIELDS[$sort['field']];
            $query->orderBy("{$meta['table']}.{$meta['column']}", $sort['direction']);
        }

        $query->limit($limit);

        $rows = $this->rowsToArrays($query);

        return $this->applyCustomMetrics($rows, $customMetrics);
    }

    /** @param list<string> $fields */
    private function applyRequiredJoins(Builder $query, string $table, array $fields): void
    {
        $neededTables = collect($fields)->map(fn ($f) => self::ALLOWED_FIELDS[$f]['table'])->unique()->all();
        foreach ($neededTables as $needed) {
            if ($needed === $table) {
                continue;
            }
            $join = self::JOINS[$table][$needed] ?? null;
            if ($join === null) {
                throw ValidationException::withMessages([
                    'fields' => "Field crossing from {$table} to {$needed} is not in the join allowlist.",
                ]);
            }
            $query->join($needed, $join[0], $join[1], $join[2]);
        }
    }

    /** @param list<array{field: string, op: string, value: mixed}> $filters */
    private function applyFiltersToQuery(Builder $query, array $filters): void
    {
        foreach ($filters as $filter) {
            $meta = self::ALLOWED_FIELDS[$filter['field']];
            $qualifiedField = "{$meta['table']}.{$meta['column']}";

            if ($filter['op'] === 'in' || $filter['op'] === 'not_in') {
                $values = (array) $filter['value'];
                if ($filter['op'] === 'in') {
                    $query->whereIn($qualifiedField, $values);
                } else {
                    $query->whereNotIn($qualifiedField, $values);
                }

                continue;
            }

            $query->where($qualifiedField, $filter['op'], $filter['value']);
        }
    }

    /**
     * Phase-50 CUSTOM-METRICS-3: resolve active landlord metrics. Unknown slugs throw.
     *
     * @param  list<mixed>  $slugs
     * @return list<array{slug: string, name: string, rpn: array}>
     */
    private function validateCustomMetrics(array $slugs, int $landlordId): array
    {
        if ($slugs === []) {
            return [];
        }
        $cleaned = [];
        foreach ($slugs as $i => $slug) {
            if (! is_string($slug) || $slug === '') {
                throw ValidationException::withMessages(["custom_metrics.{$i}" => 'Metric slug must be a string.']);
            }
            $cleaned[] = $slug;
        }
        $cleaned = array_values(array_unique($cleaned));

        $rows = ReportMetric::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->whereIn('slug', $cleaned)
            ->get(['slug', 'name', 'parsed_rpn']);

        $found = $rows->pluck('slug')->all();
        $missing = array_diff($cleaned, $found);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'custom_metrics' => 'Unknown or inactive metric(s): '.implode(', ', $missing),
            ]);
        }

        return $rows->map(fn ($r) => [
            'slug' => $r->slug,
            'name' => $r->name,
            'rpn' => $r->parsed_rpn,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function applyCustomMetrics(array $rows, array $metrics): array
    {
        if ($metrics === [] || $rows === []) {
            return $rows;
        }
        foreach ($rows as $i => $row) {
            $fieldKeyed = [];
            foreach ($row as $k => $v) {
                $fieldKeyed[str_replace('_', '.', (string) $k)] = $v;
            }
            foreach ($metrics as $metric) {
                $col = 'metric_'.$metric['slug'];
                $rows[$i][$col] = $this->metricFormulas->evaluate($metric['rpn'], $fieldKeyed);
            }
        }

        return $rows;
    }

    private function requireTable(mixed $table): string
    {
        if (! is_string($table) || ! in_array($table, self::ALLOWED_TABLES, true)) {
            throw ValidationException::withMessages([
                'table' => 'Table must be one of: '.implode(', ', self::ALLOWED_TABLES),
            ]);
        }

        return $table;
    }

    /** @return list<string> */
    private function requireFields(array $fields): array
    {
        if ($fields === []) {
            throw ValidationException::withMessages(['fields' => 'At least one field is required.']);
        }

        $invalid = array_filter($fields, fn ($f) => ! is_string($f) || ! array_key_exists($f, self::ALLOWED_FIELDS));
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'fields' => 'Unknown field(s): '.implode(', ', array_map('strval', $invalid)),
            ]);
        }

        return array_values(array_unique($fields));
    }

    /** @return list<array{field: string, op: string, value: mixed}> */
    private function validateFilters(array $filters): array
    {
        $out = [];
        foreach ($filters as $i => $filter) {
            $out[] = $this->validateSingleFilter($i, $filter);
        }

        return $out;
    }

    /** @return array{field: string, op: string, value: mixed} */
    private function validateSingleFilter(int $i, mixed $filter): array
    {
        if (! is_array($filter)) {
            throw ValidationException::withMessages(["filters.{$i}" => 'Filter must be an object.']);
        }
        $field = $filter['field'] ?? null;
        $op = $filter['op'] ?? null;
        $value = $filter['value'] ?? null;

        if (! is_string($field) || ! array_key_exists($field, self::ALLOWED_FIELDS)) {
            throw ValidationException::withMessages(["filters.{$i}.field" => 'Unknown field.']);
        }

        $type = self::ALLOWED_FIELDS[$field]['type'];
        $this->requireAllowedOperator($i, $type, $op);

        $this->validateFilterValue($i, $type, $op, $value);

        return ['field' => $field, 'op' => $op, 'value' => $value];
    }

    private function requireAllowedOperator(int $i, string $type, mixed $op): void
    {
        $allowedOps = $this->allowedOperatorsForType($type);

        if (! is_string($op) || ! in_array($op, $allowedOps, true)) {
            throw ValidationException::withMessages([
                "filters.{$i}.op" => "Operator '{$op}' is not allowed for {$type} field.",
            ]);
        }
    }

    /** @return list<string> */
    private function allowedOperatorsForType(string $type): array
    {
        return match ($type) {
            'numeric' => self::NUMERIC_OPERATORS,
            'date' => self::DATE_OPERATORS,
            'string' => self::STRING_OPERATORS,
            'boolean' => self::BOOLEAN_OPERATORS,
            default => [],
        };
    }

    private function validateFilterValue(int $i, string $type, string $op, mixed $value): void
    {
        if ($op === 'in' || $op === 'not_in') {
            if (! is_array($value) || $value === []) {
                throw ValidationException::withMessages(["filters.{$i}.value" => "Value must be a non-empty array for {$op}."]);
            }
            foreach ($value as $j => $v) {
                $this->validateScalar($type, $v, "filters.{$i}.value.{$j}");
            }

            return;
        }
        $this->validateScalar($type, $value, "filters.{$i}.value");
    }

    private function validateScalar(string $type, mixed $value, string $key): void
    {
        $ok = $this->isValidScalarForType($type, $value);

        if (! $ok) {
            throw ValidationException::withMessages([$key => "Value is not a valid {$type}."]);
        }
    }

    private function isValidScalarForType(string $type, mixed $value): bool
    {
        return match ($type) {
            'numeric' => $this->isValidNumeric($value),
            'date' => $this->isValidDate($value),
            'string' => $this->isValidString($value),
            'boolean' => $this->isValidBoolean($value),
            default => false,
        };
    }

    private function isValidNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }

    private function isValidDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(T.*)?$/', $value) === 1;
    }

    private function isValidString(mixed $value): bool
    {
        return is_string($value) && mb_strlen($value) <= 100;
    }

    private function isValidBoolean(mixed $value): bool
    {
        return is_bool($value) || $value === 0 || $value === 1;
    }

    /** @return list<string> */
    private function validateGroupBy(array $groupBy): array
    {
        $out = [];
        foreach ($groupBy as $i => $field) {
            if (! is_string($field) || ! array_key_exists($field, self::ALLOWED_FIELDS)) {
                throw ValidationException::withMessages(["group_by.{$i}" => 'Unknown group-by field.']);
            }
            $out[] = $field;
        }

        return $out;
    }

    /** @return list<array{field: string, direction: string}> */
    private function validateSortBy(array $sortBy): array
    {
        $out = [];
        foreach ($sortBy as $i => $sort) {
            if (! is_array($sort)) {
                throw ValidationException::withMessages(["sort_by.{$i}" => 'Sort entry must be an object.']);
            }
            $field = $sort['field'] ?? null;
            $direction = strtolower((string) ($sort['direction'] ?? 'asc'));

            if (! is_string($field) || ! array_key_exists($field, self::ALLOWED_FIELDS)) {
                throw ValidationException::withMessages(["sort_by.{$i}.field" => 'Unknown sort field.']);
            }
            if (! in_array($direction, self::SORT_DIRECTIONS, true)) {
                throw ValidationException::withMessages(["sort_by.{$i}.direction" => 'Direction must be asc or desc.']);
            }

            $out[] = ['field' => $field, 'direction' => $direction];
        }

        return $out;
    }

    private function validateLimit(mixed $limit): int
    {
        if (! is_int($limit) || $limit < 1 || $limit > 10000) {
            throw ValidationException::withMessages([
                'limit' => 'Limit must be an integer 1-10000.',
            ]);
        }

        return $limit;
    }

    /** @return list<array<string, mixed>> */
    private function rowsToArrays(Builder $query): array
    {
        return $query->get()->map(fn ($row) => (array) $row)->all();
    }
}
