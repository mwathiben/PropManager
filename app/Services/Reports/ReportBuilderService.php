<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase-27 BI-BUILDER-3: SAFE SQL generator for the custom report
 * builder.
 *
 * Critical security surface. The builder UI lets a landlord pick
 * fields / filters / group-by from a curated list; this service
 * compiles that picker output into a query. Two defence-in-depth
 * rules govern this file:
 *
 *   1. EVERY field name, table name, operator, and group-by reference
 *      is validated against an in-file allowlist (the constants
 *      below). Anything not on the list throws ValidationException
 *      at the entrance — the query never starts.
 *   2. EVERY user-supplied value flows through ->where() / ->wherein()
 *      binding parameters — never string-concatenated into SQL. The
 *      service NEVER calls DB::raw() with any value from the request.
 *
 * If a future contributor edits this file: those two rules are the
 * contract. The Phase27BuilderInjectionTest watchdog fires 20+
 * classic injection payloads at every input slot and asserts each
 * is rejected. A failing case there means a security regression —
 * fix the validation, do not loosen the test.
 *
 * Allowlist extension protocol:
 *   - Add the field to ALLOWED_FIELDS with its (table, column) pair.
 *   - Add the table to ALLOWED_TABLES if new.
 *   - Add the join shape to JOINS if it crosses tables.
 *   - Update docs/runbooks/bi.md "Field allowlist" section.
 *   - Phase27BuilderInjectionTest covers the new field automatically
 *     via test_field_allowlist_is_locked.
 */
class ReportBuilderService
{
    public const ALLOWED_TABLES = ['payments', 'invoices', 'leases'];

    /**
     * field_key => [table, column, type, label]
     * - field_key is the public-facing identifier the UI picker sends
     * - table must be in ALLOWED_TABLES
     * - column must match a real DB column (validated against schema)
     * - type drives operator restrictions (numeric vs date vs string)
     */
    public const ALLOWED_FIELDS = [
        'payment.amount' => ['table' => 'payments', 'column' => 'amount', 'type' => 'numeric', 'label' => 'Payment amount'],
        'payment.payment_date' => ['table' => 'payments', 'column' => 'payment_date', 'type' => 'date', 'label' => 'Payment date'],
        'payment.payment_method' => ['table' => 'payments', 'column' => 'payment_method', 'type' => 'string', 'label' => 'Payment method'],
        'invoice.total_due' => ['table' => 'invoices', 'column' => 'total_due', 'type' => 'numeric', 'label' => 'Invoice total'],
        'invoice.amount_paid' => ['table' => 'invoices', 'column' => 'amount_paid', 'type' => 'numeric', 'label' => 'Invoice amount paid'],
        'invoice.status' => ['table' => 'invoices', 'column' => 'status', 'type' => 'string', 'label' => 'Invoice status'],
        'invoice.due_date' => ['table' => 'invoices', 'column' => 'due_date', 'type' => 'date', 'label' => 'Invoice due date'],
        'lease.rent_amount' => ['table' => 'leases', 'column' => 'rent_amount', 'type' => 'numeric', 'label' => 'Lease rent amount'],
        'lease.start_date' => ['table' => 'leases', 'column' => 'start_date', 'type' => 'date', 'label' => 'Lease start date'],
        'lease.is_active' => ['table' => 'leases', 'column' => 'is_active', 'type' => 'boolean', 'label' => 'Lease active'],
    ];

    public const NUMERIC_OPERATORS = ['=', '!=', '<', '<=', '>', '>='];

    public const DATE_OPERATORS = ['=', '!=', '<', '<=', '>', '>='];

    public const STRING_OPERATORS = ['=', '!=', 'in', 'not_in'];

    public const BOOLEAN_OPERATORS = ['=', '!='];

    public const SORT_DIRECTIONS = ['asc', 'desc'];

    /**
     * Pre-defined joins, keyed by (root_table → joined_table). Only
     * these table-pairs are allowed to be joined; the builder cannot
     * synthesise arbitrary JOINs.
     */
    private const JOINS = [
        'payments' => [
            'leases' => ['payments.lease_id', '=', 'leases.id'],
            'invoices' => ['payments.invoice_id', '=', 'invoices.id'],
        ],
        'invoices' => [
            'leases' => ['invoices.lease_id', '=', 'leases.id'],
        ],
    ];

    /**
     * Compile + execute the report. Returns the rows as plain arrays.
     *
     * @param  array{table: string, fields: list<string>, filters?: list<array{field: string, op: string, value: mixed}>, group_by?: list<string>, sort_by?: list<array{field: string, direction: string}>, limit?: int}  $config
     * @return list<array<string, mixed>>
     */
    public function run(array $config, int $landlordId): array
    {
        $table = $this->requireTable($config['table'] ?? null);
        $fields = $this->requireFields((array) ($config['fields'] ?? []));
        $filters = $this->validateFilters((array) ($config['filters'] ?? []));
        $groupBy = $this->validateGroupBy((array) ($config['group_by'] ?? []));
        $sortBy = $this->validateSortBy((array) ($config['sort_by'] ?? []));
        $limit = $this->validateLimit(array_key_exists('limit', $config) ? $config['limit'] : 200);

        $query = DB::table($table);

        // Joins are only added for tables that actually need them
        // (i.e. fields reference a different table from $table).
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

        // CRITICAL: landlord scoping is mandatory. The Phase27Builder
        // tests assert this — there is no escape hatch.
        $query->where("{$table}.landlord_id", '=', $landlordId);

        // Filters. Every filter is parameterised through Eloquent's
        // builder — no DB::raw() with user input anywhere.
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

        // Select list — always qualified, always from the allowlist.
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

        return $this->rowsToArrays($query);
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

    /**
     * @param  list<mixed>  $fields
     * @return list<string>
     */
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

        /** @var list<string> $cleaned */
        $cleaned = array_values(array_unique($fields));

        return $cleaned;
    }

    /**
     * @param  list<mixed>  $filters
     * @return list<array{field: string, op: string, value: mixed}>
     */
    private function validateFilters(array $filters): array
    {
        $out = [];
        foreach ($filters as $i => $filter) {
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
            $allowedOps = match ($type) {
                'numeric' => self::NUMERIC_OPERATORS,
                'date' => self::DATE_OPERATORS,
                'string' => self::STRING_OPERATORS,
                'boolean' => self::BOOLEAN_OPERATORS,
                default => [],
            };

            if (! is_string($op) || ! in_array($op, $allowedOps, true)) {
                throw ValidationException::withMessages([
                    "filters.{$i}.op" => "Operator '{$op}' is not allowed for {$type} field.",
                ]);
            }

            // Value-side validation by type.
            $this->validateFilterValue($i, $type, $op, $value);

            $out[] = ['field' => $field, 'op' => $op, 'value' => $value];
        }

        return $out;
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
        $ok = match ($type) {
            'numeric' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            'date' => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(T.*)?$/', $value) === 1,
            'string' => is_string($value) && mb_strlen($value) <= 100,
            'boolean' => is_bool($value) || $value === 0 || $value === 1,
            default => false,
        };

        if (! $ok) {
            throw ValidationException::withMessages([$key => "Value is not a valid {$type}."]);
        }
    }

    /**
     * @param  list<mixed>  $groupBy
     * @return list<string>
     */
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

    /**
     * @param  list<mixed>  $sortBy
     * @return list<array{field: string, direction: string}>
     */
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

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsToArrays(Builder $query): array
    {
        return $query->get()->map(fn ($row) => (array) $row)->all();
    }
}
