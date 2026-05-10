<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Generate a new secret key for the user.
     */
    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Get the QR code URL for the user.
     */
    public function getQrCodeUrl(User $user, string $secret): string
    {
        $issuer = config('security.two_factor.issuer', config('app.name'));

        return $this->google2fa->getQRCodeUrl(
            $issuer,
            $user->email,
            $secret
        );
    }

    /**
     * Generate a QR code SVG for the user.
     */
    public function getQrCodeSvg(User $user, string $secret): string
    {
        $url = $this->getQrCodeUrl($user, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Verify a TOTP code against the user's secret.
     */
    public function verify(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        $secret = decrypt($user->two_factor_secret);
        $window = config('security.two_factor.window', 1);

        return $this->google2fa->verifyKey($secret, $code, $window);
    }

    /**
     * Verify a code against a given secret (used during setup).
     */
    public function verifySecret(string $secret, string $code): bool
    {
        $window = config('security.two_factor.window', 1);

        return $this->google2fa->verifyKey($secret, $code, $window);
    }

    /**
     * Enable 2FA for the user.
     */
    public function enable(User $user, string $secret): void
    {
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt($this->generateRecoveryCodes()->toJson()),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    /**
     * Disable 2FA for the user.
     */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Generate recovery codes.
     */
    public function generateRecoveryCodes(): Collection
    {
        $count = config('security.two_factor.backup_codes_count', 8);

        return Collection::times($count, function () {
            return $this->generateRecoveryCode();
        });
    }

    /**
     * Generate a single recovery code.
     *
     * CRYPTO-7: Str::random uses random_bytes under the hood today, but
     * the alphabet (a-zA-Z0-9) gives only ~5.95 bits per char. Using
     * random_bytes(5)+bin2hex yields a uniform 40-bit code per segment
     * with no alphabet ambiguity (no l/I/0/O collisions when typed).
     */
    protected function generateRecoveryCode(): string
    {
        return strtoupper(implode('-', [
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
        ]));
    }

    /**
     * Get the user's recovery codes.
     */
    public function getRecoveryCodes(User $user): Collection
    {
        if (! $user->two_factor_recovery_codes) {
            return collect();
        }

        return collect(json_decode(decrypt($user->two_factor_recovery_codes), true));
    }

    /**
     * Regenerate recovery codes for the user.
     */
    public function regenerateRecoveryCodes(User $user): Collection
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt($codes->toJson()),
        ])->save();

        return $codes;
    }

    /**
     * Verify a recovery code.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = $this->getRecoveryCodes($user);

        $normalizedCode = Str::upper(str_replace(' ', '', $code));

        // CRYPTO-7: constant-time match. ::contains short-circuits on
        // first match, leaking the index of the matched code via timing
        // — small but real. hash_equals folds the whole list before
        // returning so the wall-clock duration is independent of which
        // (if any) code matched.
        $matched = false;
        foreach ($codes as $candidate) {
            if (hash_equals((string) $candidate, $normalizedCode)) {
                $matched = true;
            }
        }

        if (! $matched) {
            return false;
        }

        // Remove used code
        $remainingCodes = $codes->reject(fn ($c) => $c === $normalizedCode);

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt($remainingCodes->values()->toJson()),
        ])->save();

        return true;
    }

    /**
     * Check if 2FA is enabled for the user.
     */
    public function isEnabled(User $user): bool
    {
        return ! is_null($user->two_factor_confirmed_at);
    }

    /**
     * Check if the user has confirmed 2FA.
     */
    public function isConfirmed(User $user): bool
    {
        return ! is_null($user->two_factor_confirmed_at);
    }

    /**
     * Check if 2FA is required for the user based on their role.
     */
    public function isRequired(User $user): bool
    {
        if (! config('security.two_factor.enabled', true)) {
            return false;
        }

        // Check if user has explicitly enforced 2FA
        if ($user->two_factor_enforced) {
            return true;
        }

        // Check role-based enforcement
        $enforcedRoles = config('security.two_factor.enforced_roles', []);

        return in_array($user->role, $enforcedRoles);
    }

    /**
     * Check if the user needs to complete 2FA setup.
     */
    public function needsSetup(User $user): bool
    {
        return $this->isRequired($user) && ! $this->isEnabled($user);
    }
}
