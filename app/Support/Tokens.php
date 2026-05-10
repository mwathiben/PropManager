<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CRYPTO-8: single chokepoint for ALL secret-token generation.
 *
 * Pre-fix three different models hand-rolled their own generators
 * (Str::random, bin2hex(random_bytes(32))). Centralising means any
 * future audit (entropy, alphabet, length) lands in one file and
 * makes it trivial to confirm no caller fell back to a weaker
 * primitive like mt_rand or uniqid.
 */
final class Tokens
{
    /**
     * URL-safe hex token. 32 bytes = 256 bits of entropy = collision-
     * resistant and brute-force-resistant for invitation/link tokens.
     */
    public static function secure(int $bytes = 32): string
    {
        if ($bytes < 16) {
            throw new \InvalidArgumentException('Token entropy must be at least 16 bytes (128 bits).');
        }

        return bin2hex(random_bytes($bytes));
    }
}
