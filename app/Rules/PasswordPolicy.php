<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

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
     */
    protected function isBreachedPassword(string $password): bool
    {
        try {
            $sha1 = strtoupper(sha1($password));
            $prefix = substr($sha1, 0, 5);
            $suffix = substr($sha1, 5);

            $response = Http::timeout(5)
                ->get("https://api.pwnedpasswords.com/range/{$prefix}");

            if (! $response->successful()) {
                // If API fails, don't block the user
                return false;
            }

            $hashes = explode("\n", $response->body());

            foreach ($hashes as $hash) {
                [$hashSuffix, $count] = explode(':', trim($hash));
                if (strtoupper($hashSuffix) === $suffix) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            // If anything fails, don't block the user
            return false;
        }
    }
}
