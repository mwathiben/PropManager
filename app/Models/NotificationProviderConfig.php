<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NotificationProviderConfig extends Model
{
    use HasFactory, TenantScope;

    public const TYPE_EMAIL = 'email';

    public const TYPE_SMS = 'sms';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_PUSH = 'push';

    public const PROVIDER_TYPES = [
        self::TYPE_EMAIL,
        self::TYPE_SMS,
        self::TYPE_WHATSAPP,
        self::TYPE_PUSH,
    ];

    public const SMS_PROVIDERS = ['twilio', 'africas_talking'];

    public const EMAIL_PROVIDERS = ['smtp', 'mailgun', 'sendgrid', 'ses'];

    protected $fillable = [
        'landlord_id',
        'provider_type',
        'provider_name',
        'credentials',
        'is_enabled',
        'is_verified',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_verified' => 'boolean',
        'settings' => 'array',
    ];

    protected $hidden = [
        'credentials',
    ];

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function setCredentialsAttribute($value): void
    {
        $this->attributes['credentials'] = $value ? Crypt::encryptString(json_encode($value)) : null;
    }

    public function getCredentialsAttribute($value): ?array
    {
        if (! $value) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getCredential(string $key, $default = null)
    {
        return $this->credentials[$key] ?? $default;
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public static function forLandlord(int $landlordId, string $providerType): ?self
    {
        return static::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('provider_type', $providerType)
            ->first();
    }

    public static function getOrCreate(int $landlordId, string $providerType): self
    {
        return static::withoutGlobalScope('landlord')->firstOrCreate(
            ['landlord_id' => $landlordId, 'provider_type' => $providerType],
            ['is_enabled' => false, 'is_verified' => false]
        );
    }

    public function isConfigured(): bool
    {
        if (! $this->provider_name) {
            return false;
        }

        $credentials = $this->credentials;
        if (! $credentials || empty($credentials)) {
            return false;
        }

        return match ($this->provider_type) {
            self::TYPE_SMS => $this->hasSmsCredentials($credentials),
            self::TYPE_WHATSAPP => $this->hasWhatsAppCredentials($credentials),
            self::TYPE_EMAIL => $this->hasEmailCredentials($credentials),
            self::TYPE_PUSH => $this->hasPushCredentials($credentials),
            default => false,
        };
    }

    private function hasSmsCredentials(array $credentials): bool
    {
        return match ($this->provider_name) {
            'twilio' => isset($credentials['account_sid'], $credentials['auth_token'], $credentials['phone_number']),
            'africas_talking' => isset($credentials['api_key'], $credentials['username']),
            default => false,
        };
    }

    private function hasWhatsAppCredentials(array $credentials): bool
    {
        return isset($credentials['account_sid'], $credentials['auth_token'], $credentials['whatsapp_number']);
    }

    private function hasEmailCredentials(array $credentials): bool
    {
        return isset($credentials['host'], $credentials['port'], $credentials['username'], $credentials['password']);
    }

    private function hasPushCredentials(array $credentials): bool
    {
        return isset($credentials['vapid_public_key'], $credentials['vapid_private_key']);
    }

    public function markAsVerified(): self
    {
        $this->is_verified = true;
        $this->save();

        return $this;
    }

    public function enable(): self
    {
        $this->is_enabled = true;
        $this->save();

        return $this;
    }

    public function disable(): self
    {
        $this->is_enabled = false;
        $this->save();

        return $this;
    }
}
