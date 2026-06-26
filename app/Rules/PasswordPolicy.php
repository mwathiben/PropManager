<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PasswordPolicy implements ValidationRule
{
    protected array $errors = [];

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $config = config('security.password');

        if ($message = $this->failingComplexityCheck($config, $value)) {
            $fail($message);

            return;
        }

        if ($this->isCommonPassword($value)) {
            $fail('This password is too common. Please choose a more unique password.');

            return;
        }

        if ($this->shouldBlockAsBreached($config, $value)) {
            $fail('This password has appeared in a data breach. Please choose a different password.');

            return;
        }
    }

    /**
     * Run length and character-class checks; return the first failing message or null.
     */
    private function failingComplexityCheck(array $config, string $value): ?string
    {
        if ($message = $this->checkMinLength($config['min_length'] ?? 12, $value)) {
            return $message;
        }

        foreach ($this->charClassRules($config) as [$pattern, $message]) {
            if (! preg_match($pattern, $value)) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Return the minimum-length error message, or null when the value is long enough.
     */
    private function checkMinLength(int $minLength, string $value): ?string
    {
        if (strlen($value) < $minLength) {
            return "The password must be at least {$minLength} characters.";
        }

        return null;
    }

    /**
     * Return the enabled character-class rules as [pattern, message] pairs.
     */
    private function charClassRules(array $config): array
    {
        $rules = [];

        if ($config['require_uppercase'] ?? true) {
            $rules[] = ['/[A-Z]/', 'The password must contain at least one uppercase letter.'];
        }

        if ($config['require_lowercase'] ?? true) {
            $rules[] = ['/[a-z]/', 'The password must contain at least one lowercase letter.'];
        }

        if ($config['require_numbers'] ?? true) {
            $rules[] = ['/[0-9]/', 'The password must contain at least one number.'];
        }

        if ($config['require_symbols'] ?? true) {
            $rules[] = ['/[^A-Za-z0-9]/', 'The password must contain at least one special character.'];
        }

        return $rules;
    }

    /**
     * Return true when the breach check is enabled and the password is found breached.
     */
    private function shouldBlockAsBreached(array $config, string $value): bool
    {
        return ($config['check_breached'] ?? true) && $this->isBreachedPassword($value);
    }

    /**
     * Check if password is in a list of common passwords.
     */
    protected function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', 'password1', 'password123', '12345678', '123456789',
            'qwerty123', 'qwertyuiop', 'letmein', 'welcome', 'admin123',
            'monkey', 'dragon', 'master', 'login', 'princess', 'passw0rd',
            'abc123', 'iloveyou', 'trustno1', 'sunshine', 'football',
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * Check if password has been breached using Have I Been Pwned API.
     *
     * Uses k-Anonymity model - only sends first 5 chars of SHA1 hash.
     *
     * HANDLE-11: failure modes are now visible. We cache successful range
     * results for 10 minutes (so a flap doesn't open a fail-open window any
     * wider than necessary) and emit a warning log on outage so ops can see
     * how often the HIBP check is bypassed.
     */
    protected function isBreachedPassword(string $password): bool
    {
        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        try {
            $body = $this->fetchHibpRangeBody($prefix);

            return $body !== null && $this->suffixFoundInBody($body, $suffix);
        } catch (\Throwable $e) {
            Log::channel('security')->warning('HIBP check failed; failing open', [
                'prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Fetch the HIBP range response body for a given 5-char SHA1 prefix.
     *
     * Returns null when the API returns a non-success status (fail-open).
     * Results are cached for 10 minutes to limit exposure during a flap.
     */
    private function fetchHibpRangeBody(string $prefix): ?string
    {
        return Cache::remember(
            "hibp:range:{$prefix}",
            now()->addMinutes(10),
            function () use ($prefix) {
                $response = Http::connectTimeout(2)
                    ->timeout(5)
                    ->get("https://api.pwnedpasswords.com/range/{$prefix}");

                if (! $response->successful()) {
                    Log::channel('security')->warning('HIBP range fetch returned non-success; failing open', [
                        'prefix' => $prefix,
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                return $response->body();
            }
        );
    }

    /**
     * Return true when the SHA1 suffix appears in the HIBP range response body.
     */
    private function suffixFoundInBody(string $body, string $suffix): bool
    {
        foreach (explode("\n", $body) as $hash) {
            $parts = explode(':', trim($hash));

            if (count($parts) < 2) {
                continue;
            }

            if (strtoupper($parts[0]) === $suffix) {
                return true;
            }
        }

        return false;
    }
}
