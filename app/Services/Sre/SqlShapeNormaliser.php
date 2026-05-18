<?php

declare(strict_types=1);

namespace App\Services\Sre;

/**
 * Phase-57 SLOW-QUERY-2: collapse SQL queries down to their shape by
 * stripping literals + collapsing IN() lists.
 *
 * Shape = a SQL string with all literal values replaced by ?.
 *   - 'SELECT * FROM users WHERE id = 123'         → 'SELECT * FROM users WHERE id = ?'
 *   - 'SELECT * FROM users WHERE id IN (1, 2, 3)'  → 'SELECT * FROM users WHERE id IN (?)'
 *   - "SELECT * FROM users WHERE name = 'alice'"   → 'SELECT * FROM users WHERE name = ?'
 *
 * Truncates to 500 characters so the sql_shape column never overflows.
 * Order of operations matters: strip strings before numerics to avoid
 * matching numeric prefixes inside string literals.
 */
class SqlShapeNormaliser
{
    public const MAX_SHAPE_LENGTH = 500;

    public function normalise(string $sql): string
    {
        // Strip single-quoted strings (with simple two-char-escape support).
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql) ?? $sql;

        // Strip double-quoted strings (rare in MySQL bindings but possible).
        $sql = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '?', $sql) ?? $sql;

        // Collapse IN (?, ?, ?, ?, ?) → IN (?)
        $sql = preg_replace('/\bIN\s*\(\s*(?:\?\s*,\s*)+\?\s*\)/i', 'IN (?)', $sql) ?? $sql;

        // Strip integer / decimal literals.
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql) ?? $sql;

        // Collapse IN (?, ?, ?) of numeric origin (after numeric strip).
        $sql = preg_replace('/\bIN\s*\(\s*(?:\?\s*,\s*)+\?\s*\)/i', 'IN (?)', $sql) ?? $sql;

        // Collapse runs of whitespace.
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

        if (strlen($sql) > self::MAX_SHAPE_LENGTH) {
            $sql = substr($sql, 0, self::MAX_SHAPE_LENGTH);
        }

        return $sql;
    }
}
