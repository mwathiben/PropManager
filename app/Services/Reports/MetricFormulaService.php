<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Validation\ValidationException;

/**
 * Phase-50 CUSTOM-METRICS-2: safe formula DSL for landlord-defined
 * report metrics.
 *
 * Critical security surface. This service compiles a landlord-supplied
 * expression string into RPN (reverse Polish notation) and evaluates
 * it row-by-row. The four contract rules govern this file:
 *
 *   1. NO eval() / preg_replace_callback /e / create_function / dynamic
 *      method dispatch ANYWHERE in this file. We are a hand-rolled
 *      tokeniser + Shunting-Yard parser + RPN evaluator.
 *   2. Tokens are restricted to: number literals, field references
 *      {table.col} where the inner key MUST be in
 *      ReportBuilderService::ALLOWED_FIELDS, parentheses, and the
 *      five arithmetic operators (+ - * / %).
 *   3. No function calls, no variable assignment, no string ops, no
 *      bitwise ops. Anything outside the above lexer alphabet throws
 *      ValidationException at parse time.
 *   4. Recursion depth is capped at 128 tokens and 64 nested parens.
 *      Expressions exceeding either throw — DoS guard.
 *
 * The Phase50MetricFormulaInjectionTest watchdog throws classic
 * eval-escape payloads (`system('rm -rf /')`, `${jndi:...}`,
 * `'; DROP TABLE users; --`, `\\x00`, etc.) at every input slot and
 * asserts each is rejected.
 *
 * If a future contributor edits this file: the above rules are the
 * contract. A failing watchdog case means a security regression —
 * fix the lexer/parser, do not loosen the test.
 */
class MetricFormulaService
{
    private const MAX_TOKENS = 128;

    private const MAX_PAREN_DEPTH = 64;

    private const OPERATORS = [
        '+' => ['precedence' => 1, 'right_assoc' => false],
        '-' => ['precedence' => 1, 'right_assoc' => false],
        '*' => ['precedence' => 2, 'right_assoc' => false],
        '/' => ['precedence' => 2, 'right_assoc' => false],
        '%' => ['precedence' => 2, 'right_assoc' => false],
    ];

    /**
     * Parse expression into RPN token list. Each RPN token is an array:
     *   ['type' => 'number', 'value' => 1.5]
     *   ['type' => 'field',  'value' => 'payment.amount']
     *   ['type' => 'op',     'value' => '+']
     *
     * @return list<array{type: string, value: mixed}>
     */
    public function parse(string $expression): array
    {
        $tokens = $this->tokenise($expression);
        $this->assertFieldRefsInAllowlist($tokens);

        return $this->shuntingYard($tokens);
    }

    /**
     * Evaluate parsed RPN against a single row.
     *
     * @param  list<array{type: string, value: mixed}>  $rpn
     * @param  array<string, mixed>  $row  ALLOWED_FIELDS key => scalar
     */
    public function evaluate(array $rpn, array $row): float
    {
        if ($rpn === []) {
            throw ValidationException::withMessages(['expression' => 'Empty expression.']);
        }

        $stack = [];
        foreach ($rpn as $token) {
            if ($token['type'] === 'number') {
                $stack[] = (float) $token['value'];

                continue;
            }
            if ($token['type'] === 'field') {
                $key = (string) $token['value'];
                if (! array_key_exists($key, $row)) {
                    throw ValidationException::withMessages([
                        'expression' => "Row missing field '{$key}'.",
                    ]);
                }
                $stack[] = is_numeric($row[$key]) ? (float) $row[$key] : 0.0;

                continue;
            }
            // op
            if (count($stack) < 2) {
                throw ValidationException::withMessages(['expression' => 'Malformed expression.']);
            }
            $b = array_pop($stack);
            $a = array_pop($stack);
            $stack[] = match ($token['value']) {
                '+' => $a + $b,
                '-' => $a - $b,
                '*' => $a * $b,
                '/' => $b === 0.0 ? 0.0 : $a / $b,
                '%' => $b === 0.0 ? 0.0 : fmod($a, $b),
                default => throw ValidationException::withMessages([
                    'expression' => "Unknown operator '{$token['value']}'.",
                ]),
            };
        }

        if (count($stack) !== 1) {
            throw ValidationException::withMessages(['expression' => 'Expression did not reduce to a single value.']);
        }

        return $stack[0];
    }

    /**
     * @return list<array{type: string, value: mixed}>
     */
    private function tokenise(string $expression): array
    {
        $len = strlen($expression);
        if ($len === 0) {
            throw ValidationException::withMessages(['expression' => 'Expression is empty.']);
        }
        if ($len > 1024) {
            throw ValidationException::withMessages(['expression' => 'Expression exceeds 1024 characters.']);
        }

        $tokens = [];
        $i = 0;
        $parenDepth = 0;
        $maxParenDepth = 0;

        while ($i < $len) {
            $char = $expression[$i];

            if ($char === ' ' || $char === "\t") {
                $i++;

                continue;
            }

            if (isset(self::OPERATORS[$char])) {
                $tokens[] = ['type' => 'op', 'value' => $char];
                $i++;

                continue;
            }

            if ($char === '(') {
                $tokens[] = ['type' => 'lparen', 'value' => '('];
                $parenDepth++;
                $maxParenDepth = max($maxParenDepth, $parenDepth);
                if ($parenDepth > self::MAX_PAREN_DEPTH) {
                    throw ValidationException::withMessages([
                        'expression' => 'Parenthesis depth exceeds '.self::MAX_PAREN_DEPTH,
                    ]);
                }
                $i++;

                continue;
            }
            if ($char === ')') {
                if ($parenDepth === 0) {
                    throw ValidationException::withMessages(['expression' => 'Unbalanced ) at position '.$i]);
                }
                $tokens[] = ['type' => 'rparen', 'value' => ')'];
                $parenDepth--;
                $i++;

                continue;
            }

            // field reference: {table.col}
            if ($char === '{') {
                $close = strpos($expression, '}', $i + 1);
                if ($close === false) {
                    throw ValidationException::withMessages(['expression' => 'Unterminated field reference starting at '.$i]);
                }
                $inner = substr($expression, $i + 1, $close - $i - 1);
                if (! preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/', $inner)) {
                    throw ValidationException::withMessages([
                        'expression' => "Field reference '{$inner}' is malformed.",
                    ]);
                }
                $tokens[] = ['type' => 'field', 'value' => $inner];
                $i = $close + 1;

                continue;
            }

            // number literal (integer or decimal; no scientific, no signs — unary handled below)
            if (ctype_digit($char) || $char === '.') {
                $j = $i;
                $sawDot = false;
                while ($j < $len && (ctype_digit($expression[$j]) || ($expression[$j] === '.' && ! $sawDot))) {
                    if ($expression[$j] === '.') {
                        $sawDot = true;
                    }
                    $j++;
                }
                $literal = substr($expression, $i, $j - $i);
                if (! is_numeric($literal) || strlen($literal) > 20) {
                    throw ValidationException::withMessages([
                        'expression' => "Invalid or oversize number literal '{$literal}'.",
                    ]);
                }
                $tokens[] = ['type' => 'number', 'value' => (float) $literal];
                $i = $j;

                continue;
            }

            throw ValidationException::withMessages([
                'expression' => "Unexpected character '{$char}' at position {$i}.",
            ]);
        }

        if ($parenDepth !== 0) {
            throw ValidationException::withMessages(['expression' => 'Unbalanced ( in expression.']);
        }

        if (count($tokens) > self::MAX_TOKENS) {
            throw ValidationException::withMessages([
                'expression' => 'Expression exceeds '.self::MAX_TOKENS.' tokens.',
            ]);
        }

        if ($tokens === []) {
            throw ValidationException::withMessages(['expression' => 'Expression had no tokens.']);
        }

        $this->validateTokenSequence($tokens);

        return $tokens;
    }

    /**
     * Reject malformed adjacencies that the Shunting-Yard would happily
     * pass through (e.g. "1++2", "1 1", ") (", trailing op). The
     * acceptable transitions are:
     *
     *   value  → op | rparen | EOF       (value = number, field, rparen)
     *   op     → value | lparen
     *   lparen → value | lparen
     *   start  → value | lparen
     *
     * @param  list<array{type: string, value: mixed}>  $tokens
     */
    private function validateTokenSequence(array $tokens): void
    {
        $isValue = fn (?array $t) => $t !== null && in_array($t['type'], ['number', 'field', 'rparen'], true);
        $isOp = fn (?array $t) => $t !== null && $t['type'] === 'op';
        $isOpener = fn (?array $t) => $t !== null && $t['type'] === 'lparen';

        $prev = null;
        foreach ($tokens as $i => $token) {
            $type = $token['type'];
            if ($prev === null) {
                if (! ($type === 'number' || $type === 'field' || $type === 'lparen')) {
                    throw ValidationException::withMessages([
                        'expression' => "Expression cannot start with '{$token['value']}'.",
                    ]);
                }
            } elseif ($isValue($prev)) {
                if (! ($type === 'op' || $type === 'rparen')) {
                    throw ValidationException::withMessages([
                        'expression' => "Unexpected '{$token['value']}' after value at token {$i}.",
                    ]);
                }
            } elseif ($isOp($prev)) {
                if (! ($type === 'number' || $type === 'field' || $type === 'lparen')) {
                    throw ValidationException::withMessages([
                        'expression' => "Unexpected '{$token['value']}' after operator at token {$i}.",
                    ]);
                }
            } elseif ($isOpener($prev)) {
                if (! ($type === 'number' || $type === 'field' || $type === 'lparen')) {
                    throw ValidationException::withMessages([
                        'expression' => "Unexpected '{$token['value']}' after ( at token {$i}.",
                    ]);
                }
            }
            $prev = $token;
        }

        if ($prev !== null && ! $isValue($prev)) {
            throw ValidationException::withMessages([
                'expression' => 'Expression cannot end with an operator or opening parenthesis.',
            ]);
        }
    }

    /**
     * @param  list<array{type: string, value: mixed}>  $tokens
     */
    private function assertFieldRefsInAllowlist(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token['type'] !== 'field') {
                continue;
            }
            $key = (string) $token['value'];
            if (! array_key_exists($key, ReportBuilderService::ALLOWED_FIELDS)) {
                throw ValidationException::withMessages([
                    'expression' => "Field '{$key}' is not in the allowlist.",
                ]);
            }
            $type = ReportBuilderService::ALLOWED_FIELDS[$key]['type'];
            if ($type !== 'numeric') {
                throw ValidationException::withMessages([
                    'expression' => "Field '{$key}' is {$type}; only numeric fields can appear in metrics.",
                ]);
            }
        }
    }

    /**
     * Shunting-Yard: infix tokens → RPN output queue.
     *
     * @param  list<array{type: string, value: mixed}>  $tokens
     * @return list<array{type: string, value: mixed}>
     */
    private function shuntingYard(array $tokens): array
    {
        $output = [];
        $opStack = [];

        foreach ($tokens as $token) {
            if ($token['type'] === 'number' || $token['type'] === 'field') {
                $output[] = $token;

                continue;
            }
            if ($token['type'] === 'op') {
                $op = self::OPERATORS[(string) $token['value']];
                while (
                    ! empty($opStack)
                    && end($opStack)['type'] === 'op'
                    && (
                        self::OPERATORS[(string) end($opStack)['value']]['precedence'] > $op['precedence']
                        || (
                            self::OPERATORS[(string) end($opStack)['value']]['precedence'] === $op['precedence']
                            && ! $op['right_assoc']
                        )
                    )
                ) {
                    $output[] = array_pop($opStack);
                }
                $opStack[] = $token;

                continue;
            }
            if ($token['type'] === 'lparen') {
                $opStack[] = $token;

                continue;
            }
            if ($token['type'] === 'rparen') {
                while (! empty($opStack) && end($opStack)['type'] !== 'lparen') {
                    $output[] = array_pop($opStack);
                }
                if (empty($opStack)) {
                    throw ValidationException::withMessages(['expression' => 'Mismatched ) — stack drained.']);
                }
                array_pop($opStack);

                continue;
            }
        }

        while (! empty($opStack)) {
            $top = array_pop($opStack);
            if ($top['type'] === 'lparen' || $top['type'] === 'rparen') {
                throw ValidationException::withMessages(['expression' => 'Mismatched parenthesis at parse end.']);
            }
            $output[] = $top;
        }

        return $output;
    }
}
