<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\TenantPaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase-48 TENANT-PAYMENT-METHOD-2: canonical write path for tenant
 * payment-method storage. Enforces single-default-per-(user, type)
 * invariant and ensures all sensitive details flow through the
 * encrypted:json cast on TenantPaymentMethod.
 */
class TenantPaymentMethodService
{
    private const ALLOWED_TYPES = ['mpesa', 'bank', 'card'];

    public function store(User $user, string $type, array $details, bool $isDefault = false): TenantPaymentMethod
    {
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported tenant payment method type: {$type}");
        }

        return DB::transaction(function () use ($user, $type, $details, $isDefault) {
            if ($isDefault) {
                TenantPaymentMethod::where('user_id', $user->id)
                    ->where('type', $type)
                    ->update(['is_default' => false]);
            }

            return TenantPaymentMethod::updateOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                [
                    'details_encrypted' => $details,
                    'is_default' => $isDefault,
                ],
            );
        });
    }

    public function setDefault(TenantPaymentMethod $method): TenantPaymentMethod
    {
        return DB::transaction(function () use ($method) {
            TenantPaymentMethod::where('user_id', $method->user_id)
                ->where('type', $method->type)
                ->where('id', '!=', $method->id)
                ->update(['is_default' => false]);

            $method->update(['is_default' => true]);

            return $method->fresh();
        });
    }

    public function softDelete(TenantPaymentMethod $method): void
    {
        $method->delete();
    }
}
