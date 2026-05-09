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

        // Minimum length
        $minLength = $config['min_length'] ?? 12;
        if (strlen($value) < $minLength) {
            $fail("The password must be at least {$minLength} characters.");

            return;
        }

        // Uppercase requirement
        if (($config['require_uppercase'] ?? true) && ! preg_match('/[A-Z]/', $value)) {
            $fail('The password must contain at least one uppercase letter.');

            return;
        }

        // Lowercase requirement
        if (($config['require_lowercase'] ?? true) && ! preg_match('/[a-z]/', $value)) {
            $fail('The password must contain at least one lowercase letter.');

            return;
        }

        // Number requirement
        if (($config['require_numbers'] ?? true) && ! preg_match('/[0-9]/', $value)) {
            $fail('The password must contain at least one number.');

            return;
        }

        // Symbol requirement
        if (($config['require_symbols'] ?? true) && ! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('The password must contain at least one special character.');

            return;
        }

        // Check against common passwords
        if ($this->isCommonPassword($value)) {
            $fail('This password is too common. Please choose a more unique password.');

            return;
        }

        // Check if breached (Have I Been Pwned)
        if (($config['check_breached'] ?? true) && $this->isBreachedPassword($value)) {
            $fail('This password has appeared in a data breach. Please choose a different password.');

            return;
        }
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
            $body = Cache::remember(
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

            if ($body === null) {
                return false;
            }

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
        } catch (\Throwable $e) {
            Log::channel('security')->warning('HIBP check failed; failing open', [
                'prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
