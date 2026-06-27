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
            if (in_array($token['type'], ['number', 'field'], true)) {
                $stack[] = $this->resolveTokenValue($token, $row);
            } else {
                if (count($stack) < 2) {
                    throw ValidationException::withMessages(['expression' => 'Malformed expression.']);
                }
                $b = array_pop($stack);
                $a = array_pop($stack);
                $stack[] = $this->applyOperator((string) $token['value'], $a, $b);
            }
        }

        if (count($stack) !== 1) {
            throw ValidationException::withMessages(['expression' => 'Expression did not reduce to a single value.']);
        }

        return $stack[0];
    }

    private function tokenise(string $expression): array
    {
        $len = strlen($expression);
        $tokens = [];
        $i = 0;
        $parenDepth = 0;

        while ($i < $len) {
            [$token, $i, $parenDepth] = $this->lexOneChar($expression, $i, $len, $parenDepth);
            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        $this->assertTokenConstraints($expression, $parenDepth, $tokens);
        $this->validateTokenSequence($tokens);

        return $tokens;
    }

    private function assertTokenConstraints(string $expression, int $parenDepth, array $tokens): void
    {
        if (strlen($expression) === 0) {
            throw ValidationException::withMessages(['expression' => 'Expression is empty.']);
        }
        if (strlen($expression) > 1024) {
            throw ValidationException::withMessages(['expression' => 'Expression exceeds 1024 characters.']);
        }
        if ($parenDepth !== 0) {
            throw ValidationException::withMessages(['expression' => 'Unbalanced ( in expression.']);
        }
        if (count($tokens) > self::MAX_TOKENS) {
            throw ValidationException::withMessages(['expression' => 'Expression exceeds '.self::MAX_TOKENS.' tokens.']);
        }
        if ($tokens === []) {
            throw ValidationException::withMessages(['expression' => 'Expression had no tokens.']);
        }
    }

    /**
     * Consume one character at position $i and return [token|null, nextI, parenDepth].
     * Throws ValidationException for unrecognised characters.
     */
    private function lexOneChar(string $expression, int $i, int $len, int $parenDepth): array
    {
        $char = $expression[$i];

        if (strpos(" \t", $char) !== false) {
            return [null, $i + 1, $parenDepth];
        }
        if (isset(self::OPERATORS[$char])) {
            return [['type' => 'op', 'value' => $char], $i + 1, $parenDepth];
        }
        if (strpos('()', $char) !== false) {
            [$token, $parenDepth] = $this->tokeniseParen($char, $parenDepth, $i);

            return [$token, $i + 1, $parenDepth];
        }
        if ($char === '{') {
            [$token, $advance] = $this->tokeniseFieldRef($expression, $i);

            return [$token, $advance, $parenDepth];
        }
        if ($this->isNumberStart($char)) {
            [$token, $advance] = $this->tokeniseNumber($expression, $i, $len);

            return [$token, $advance, $parenDepth];
        }

        throw ValidationException::withMessages([
            'expression' => "Unexpected character '{$char}' at position {$i}.",
        ]);
    }

    /**
     * Reject malformed adjacencies (e.g. "1++2", "1 1", trailing op).
     * Transitions: value→op|rparen|EOF, op→value|lparen, lparen→value|lparen.
     */
    private function validateTokenSequence(array $tokens): void
    {
        $prev = null;
        foreach ($tokens as $i => $token) {
            if ($prev === null) {
                $this->assertValidFirstToken($token);
            } else {
                $this->assertValidTransition($prev, $token, $i);
            }
            $prev = $token;
        }

        if ($prev !== null && ! in_array($prev['type'], ['number', 'field', 'rparen'], true)) {
            throw ValidationException::withMessages([
                'expression' => 'Expression cannot end with an operator or opening parenthesis.',
            ]);
        }
    }

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

    /** Shunting-Yard: infix tokens → RPN output queue. */
    private function shuntingYard(array $tokens): array
    {
        $output = [];
        $opStack = [];

        foreach ($tokens as $token) {
            if (in_array($token['type'], ['number', 'field'], true)) {
                $output[] = $token;
            } elseif ($token['type'] === 'op') {
                $output = $this->drainHigherPrecedenceOps($output, $opStack, $token);
                $opStack[] = $token;
            } elseif ($token['type'] === 'lparen') {
                $opStack[] = $token;
            } elseif ($token['type'] === 'rparen') {
                [$output, $opStack] = $this->processRparen($output, $opStack);
            }
        }

        return $this->drainOpStack($output, $opStack);
    }

    /** Resolve a number or field token to its float value for the given row. */
    private function resolveTokenValue(array $token, array $row): float
    {
        if ($token['type'] === 'number') {
            return (float) $token['value'];
        }

        $key = (string) $token['value'];
        if (! array_key_exists($key, $row)) {
            throw ValidationException::withMessages([
                'expression' => "Row missing field '{$key}'.",
            ]);
        }

        return is_numeric($row[$key]) ? (float) $row[$key] : 0.0;
    }

    private function applyOperator(string $op, float $a, float $b): float
    {
        return match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $b === 0.0 ? 0.0 : $a / $b,
            '%' => $b === 0.0 ? 0.0 : fmod($a, $b),
            default => throw ValidationException::withMessages([
                'expression' => "Unknown operator '{$op}'.",
            ]),
        };
    }

    private function isNumberStart(string $char): bool
    {
        return ctype_digit($char) || $char === '.';
    }

    /** Tokenise a single parenthesis, returning [token, updated-depth]. */
    private function tokeniseParen(string $char, int $parenDepth, int $position): array
    {
        if ($char === '(') {
            $parenDepth++;
            if ($parenDepth > self::MAX_PAREN_DEPTH) {
                throw ValidationException::withMessages([
                    'expression' => 'Parenthesis depth exceeds '.self::MAX_PAREN_DEPTH,
                ]);
            }

            return [['type' => 'lparen', 'value' => '('], $parenDepth];
        }

        if ($parenDepth === 0) {
            throw ValidationException::withMessages(['expression' => 'Unbalanced ) at position '.$position]);
        }

        return [['type' => 'rparen', 'value' => ')'], $parenDepth - 1];
    }

    /** Tokenise a {table.col} field reference starting at position $i. */
    private function tokeniseFieldRef(string $expression, int $i): array
    {
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

        return [['type' => 'field', 'value' => $inner], $close + 1];
    }

    /** Tokenise a number literal starting at position $i, returning [token, next-i]. */
    private function tokeniseNumber(string $expression, int $i, int $len): array
    {
        $j = $this->scanNumberSpan($expression, $i, $len);
        $literal = substr($expression, $i, $j - $i);
        if (! is_numeric($literal) || strlen($literal) > 20) {
            throw ValidationException::withMessages([
                'expression' => "Invalid or oversize number literal '{$literal}'.",
            ]);
        }

        return [['type' => 'number', 'value' => (float) $literal], $j];
    }

    /** Advance past digit/dot characters; permits at most one dot. */
    private function scanNumberSpan(string $expression, int $i, int $len): int
    {
        $j = $i;
        $sawDot = false;
        while ($j < $len && (ctype_digit($expression[$j]) || ($expression[$j] === '.' && ! $sawDot))) {
            if ($expression[$j] === '.') {
                $sawDot = true;
            }
            $j++;
        }

        return $j;
    }

    private function assertValidFirstToken(array $token): void
    {
        $type = $token['type'];
        if (! ($type === 'number' || $type === 'field' || $type === 'lparen')) {
            throw ValidationException::withMessages([
                'expression' => "Expression cannot start with '{$token['value']}'.",
            ]);
        }
    }

    private function assertValidTransition(array $prev, array $token, int $i): void
    {
        $allowed = [
            'value' => ['op', 'rparen'],
            'op' => ['number', 'field', 'lparen'],
            'opener' => ['number', 'field', 'lparen'],
        ];

        $category = $this->transitionCategory($prev['type']);
        if ($category === null || in_array($token['type'], $allowed[$category], true)) {
            return;
        }

        $after = match ($category) {
            'value' => 'value',
            'op' => 'operator',
            'opener' => '(',
            default => $prev['type'],
        };
        throw ValidationException::withMessages([
            'expression' => "Unexpected '{$token['value']}' after {$after} at token {$i}.",
        ]);
    }

    /** Map a token type to its sequence-validation category, or null if not checked. */
    private function transitionCategory(string $type): ?string
    {
        return match ($type) {
            'number', 'field', 'rparen' => 'value',
            'op' => 'op',
            'lparen' => 'opener',
            default => null,
        };
    }

    /**
     * Pop operators from $opStack to $output while their precedence dominates
     * the current operator (higher precedence, or equal + left-associative).
     */
    private function drainHigherPrecedenceOps(array $output, array &$opStack, array $token): array
    {
        $op = self::OPERATORS[(string) $token['value']];
        while (! empty($opStack) && end($opStack)['type'] === 'op') {
            $topPrec = self::OPERATORS[(string) end($opStack)['value']]['precedence'];
            $dominated = $topPrec > $op['precedence']
                || ($topPrec === $op['precedence'] && ! $op['right_assoc']);
            if (! $dominated) {
                break;
            }
            $output[] = array_pop($opStack);
        }

        return $output;
    }

    /** Drain opStack into output up to the matching lparen, then discard it. */
    private function processRparen(array $output, array $opStack): array
    {
        while (! empty($opStack) && end($opStack)['type'] !== 'lparen') {
            $output[] = array_pop($opStack);
        }
        if (empty($opStack)) {
            throw ValidationException::withMessages(['expression' => 'Mismatched ) — stack drained.']);
        }
        array_pop($opStack);

        return [$output, $opStack];
    }

    /** Drain any remaining operators after all tokens are processed. */
    private function drainOpStack(array $output, array $opStack): array
    {
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
