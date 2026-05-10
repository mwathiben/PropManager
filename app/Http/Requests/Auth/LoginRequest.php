<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\SecurityLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            // RATE-8: per-email throttle in addition to the per-(email|ip)
            // one. An IP-rotating credential-stuffer can keep $this->throttleKey
            // (which contains the IP) below the 5/min cap forever; a separate
            // email-only key catches that pattern. 15 fails/hr triggers a
            // 30-min lock regardless of source IP.
            RateLimiter::hit($this->emailLockoutKey(), 60 * 30);

            // OBS-14: distinguish failure reasons in the security log.
            // The end-user message stays generic ('auth.failed') so we
            // don't leak account-existence; the SecurityLog row gets
            // the precise reason for incident-response triage.
            $email = (string) $this->string('email');
            $target = User::where('email', $email)->first();
            $reason = match (true) {
                $target === null => 'user_not_found',
                $target->email_verified_at === null => 'email_not_verified',
                ! Hash::check((string) $this->input('password'), $target->password) => 'wrong_password',
                default => 'invalid_credentials',
            };

            app(SecurityLogger::class)->logFailedLogin($email, $reason);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->emailLockoutKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $emailLocked = RateLimiter::tooManyAttempts($this->emailLockoutKey(), 15);

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5) && ! $emailLocked) {
            return;
        }

        event(new Lockout($this));

        // Log account lockout
        app(SecurityLogger::class)->logAccountLocked(
            $this->string('email'),
            $emailLocked ? 'Account locked: 15+ failed login attempts (any IP)' : 'Too many failed login attempts'
        );

        $seconds = $emailLocked
            ? RateLimiter::availableIn($this->emailLockoutKey())
            : RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /**
     * RATE-8: separate per-email lockout key (no IP) for credential
     * stuffing across rotating IPs.
     */
    public function emailLockoutKey(): string
    {
        return 'login-email:'.Str::transliterate(Str::lower($this->string('email')));
    }
}
