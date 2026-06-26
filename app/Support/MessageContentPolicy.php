<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phase-63 INBOX-MOD-3: minimal spam guard. Catches the obvious
 * patterns (URL repetition / mostly-non-printable / known tokens)
 * without committing to a full moderation engine.
 *
 * Heuristics + thresholds live in config/inbox.php so an operator can
 * tune without redeploying.
 */
class MessageContentPolicy
{
    public static function isSpam(string $body): bool
    {
        $body = trim($body);
        if ($body === '') {
            return false;
        }

        if (self::hasExcessiveUrlRepetition($body)) {
            return true;
        }

        if (self::isMostlyNonPrintable($body)) {
            return true;
        }

        if (self::matchesSpamToken($body)) {
            return true;
        }

        return false;
    }

    private static function hasExcessiveUrlRepetition(string $body): bool
    {
        $threshold = (int) config('inbox.content.url_repetition_threshold', 5);
        $count = preg_match_all('/https?:\/\/[^\s]+/i', $body, $matches);

        if ($count === false || $count <= $threshold) {
            return false;
        }

        $unique = array_unique($matches[0]);

        // Excessive repetition = many URL matches but only a couple
        // of unique URLs (classic spam pattern).
        return count($unique) <= 2;
    }

    private static function isMostlyNonPrintable(string $body): bool
    {
        $length = mb_strlen($body, 'UTF-8');
        if ($length === 0) {
            return false;
        }

        $threshold = (float) config('inbox.content.non_printable_fraction_threshold', 0.5);
        $controlMatches = preg_match_all('/\p{C}/u', $body);

        if ($controlMatches === false) {
            return false;
        }

        return ($controlMatches / $length) > $threshold;
    }

    private static function matchesSpamToken(string $body): bool
    {
        $tokens = config('inbox.content.spam_tokens', []);
        if (! self::hasValidTokenList($tokens)) {
            return false;
        }

        $lower = mb_strtolower($body, 'UTF-8');

        return self::bodyContainsAnyToken($lower, $tokens);
    }

    private static function hasValidTokenList(mixed $tokens): bool
    {
        return is_array($tokens) && $tokens !== [];
    }

    private static function bodyContainsAnyToken(string $lowerBody, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (! is_string($token) || $token === '') {
                continue;
            }

            if (str_contains($lowerBody, mb_strtolower($token, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }
}
