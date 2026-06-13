<?php

namespace App\Services\Settings;

use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\SecurityLogger;

class PaymentMethodConfigService
{
    private const SECRET_FIELDS = [
        'paystack_secret_key',
        'mpesa_passkey',
        'mpesa_consumer_key',
        'mpesa_consumer_secret',
        'mpesa_b2c_password',
        'mpesa_b2c_security_credential',
        'intasend_secret_key',
    ];

    private const LAST4_MAP = [
        'paystack_secret_key_last4' => 'paystack_secret_key',
        'mpesa_consumer_key_last4' => 'mpesa_consumer_key',
        'mpesa_consumer_secret_last4' => 'mpesa_consumer_secret',
        'intasend_secret_key_last4' => 'intasend_secret_key',
        'mpesa_b2c_password_last4' => 'mpesa_b2c_password',
        'mpesa_b2c_security_credential_last4' => 'mpesa_b2c_security_credential',
    ];

    private const UNSET_FIELDS = [
        'paystack_secret_key',
        'mpesa_consumer_key',
        'mpesa_consumer_secret',
        'mpesa_passkey',
        'mpesa_b2c_password',
        'mpesa_b2c_security_credential',
        'intasend_secret_key',
        'intasend_webhook_challenge',
    ];

    /**
     * Apply validated payment method configuration for a landlord.
     *
     * Blank-preserves existing secrets when the submitted value is empty.
     * Diffs field names only (never values) before updating, then audits
     * changed NAMES via SecurityLogger so the audit trail cannot become a
     * credentials leak.
     *
     * Returns the array of changed field names (may be empty).
     */
    public function apply(User $landlord, array $validated, SecurityLogger $logger): array
    {
        $config = PaymentConfiguration::getOrCreateForLandlord($landlord->id);

        $data = $this->dropBlankSecrets($validated);
        $changedFields = $this->diffFieldNames($config, $data);

        $config->update($data);

        if ($changedFields !== []) {
            $logger->logPaymentConfigChange(
                $landlord,
                $changedFields,
                ['landlord_id' => (int) $landlord->id]
            );
        }

        return $changedFields;
    }

    /**
     * Return the masked payment configuration array safe for the frontend.
     *
     * Adds *_last4 fields for every secret, then unset()s every raw secret
     * field so it never reaches the frontend.
     */
    public function maskedConfig(User $landlord): array
    {
        $paymentConfig = PaymentConfiguration::getOrCreateForLandlord($landlord->id);
        $data = $paymentConfig->toArray();

        $data = $this->appendLast4Fields($paymentConfig, $data);
        $data = $this->stripSecrets($data);

        return $data;
    }

    /**
     * Remove secret fields from the data array when submitted as blank,
     * so the existing encrypted value is preserved during update().
     */
    private function dropBlankSecrets(array $data): array
    {
        foreach (self::SECRET_FIELDS as $field) {
            if (empty($data[$field])) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Return the names of fields that differ between the current config
     * record and the incoming data payload.
     *
     * Non-scalars are JSON-serialised so the diff is stable and cannot
     * throw an Array-to-string conversion error.
     */
    private function diffFieldNames(PaymentConfiguration $config, array $data): array
    {
        $normalise = static fn (mixed $v): string => is_scalar($v) || $v === null
            ? (string) ($v ?? '')
            : json_encode($v);

        $changedFields = [];
        foreach ($data as $field => $value) {
            if ($normalise($config->{$field} ?? null) !== $normalise($value)) {
                $changedFields[] = $field;
            }
        }

        return $changedFields;
    }

    /**
     * Add *_last4 masked hint fields for every secret in the config.
     */
    private function appendLast4Fields(PaymentConfiguration $config, array $data): array
    {
        foreach (self::LAST4_MAP as $last4Key => $sourceField) {
            $data[$last4Key] = $config->{$sourceField}
                ? '****'.substr($config->{$sourceField}, -4)
                : null;
        }

        return $data;
    }

    /**
     * Remove raw secret fields from the data array so they never reach
     * the frontend.
     */
    private function stripSecrets(array $data): array
    {
        foreach (self::UNSET_FIELDS as $field) {
            unset($data[$field]);
        }

        return $data;
    }
}
