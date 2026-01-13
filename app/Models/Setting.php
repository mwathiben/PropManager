<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'key',
        'value',
        'is_encrypted',
        'category',
        'description',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get the decrypted value if encrypted
     */
    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Encrypt the value if marked as encrypted
     */
    public function setValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            $this->attributes['value'] = Crypt::encryptString($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Helper: Get a setting value by key for the current landlord
     */
    public static function get(string $key, $default = null, ?int $landlordId = null)
    {
        $landlordId = $landlordId ?? auth()->user()?->landlord_id ?? auth()->id();

        $setting = self::where('landlord_id', $landlordId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Helper: Set a setting value by key for the current landlord
     */
    public static function set(string $key, $value, bool $isEncrypted = false, string $category = 'general', ?string $description = null, ?int $landlordId = null): self
    {
        $landlordId = $landlordId ?? auth()->user()?->landlord_id ?? auth()->id();

        return self::updateOrCreate(
            [
                'landlord_id' => $landlordId,
                'key' => $key,
            ],
            [
                'value' => $value,
                'is_encrypted' => $isEncrypted,
                'category' => $category,
                'description' => $description,
            ]
        );
    }

    /**
     * Helper: Get all settings for a category
     */
    public static function getByCategory(string $category, ?int $landlordId = null): array
    {
        $landlordId = $landlordId ?? auth()->user()?->landlord_id ?? auth()->id();

        return self::where('landlord_id', $landlordId)
            ->where('category', $category)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Helper: Get a system-wide setting (landlord_id = NULL)
     */
    public static function getSystem(string $key, $default = null)
    {
        $setting = self::withoutGlobalScope('landlord')
            ->whereNull('landlord_id')
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Helper: Set a system-wide setting (landlord_id = NULL)
     */
    public static function setSystem(string $key, $value, bool $isEncrypted = false, string $category = 'system', ?string $description = null): self
    {
        // First, check if setting exists
        $existing = self::withoutGlobalScope('landlord')
            ->whereNull('landlord_id')
            ->where('key', $key)
            ->first();

        if ($existing) {
            $existing->is_encrypted = $isEncrypted;
            $existing->category = $category;
            $existing->description = $description;
            $existing->value = $value;
            $existing->save();

            return $existing;
        }

        // Create new system setting
        $setting = new self;
        $setting->landlord_id = null;
        $setting->key = $key;
        $setting->is_encrypted = $isEncrypted;
        $setting->category = $category;
        $setting->description = $description;
        $setting->value = $value;
        $setting->save();

        return $setting;
    }

    /**
     * Helper: Get all system settings for a category
     */
    public static function getSystemByCategory(string $category): array
    {
        return self::withoutGlobalScope('landlord')
            ->whereNull('landlord_id')
            ->where('category', $category)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Helper: Check if a system setting exists and has a non-empty value
     */
    public static function hasSystem(string $key): bool
    {
        $value = self::getSystem($key);

        return $value !== null && $value !== '';
    }
}
