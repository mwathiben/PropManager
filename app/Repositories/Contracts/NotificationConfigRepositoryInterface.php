<?php

namespace App\Repositories\Contracts;

interface NotificationConfigRepositoryInterface
{
    /**
     * Get the SMS provider for a landlord.
     *
     * @return string 'none', 'twilio', or 'africas_talking'
     */
    public function getSmsProvider(int $landlordId): string;

    /**
     * Get Twilio credentials for SMS/WhatsApp.
     *
     * @return array{account_sid: ?string, auth_token: ?string, phone_number: ?string}
     */
    public function getTwilioCredentials(int $landlordId): array;

    /**
     * Get Africa's Talking credentials for SMS.
     *
     * @return array{api_key: ?string, username: ?string, from: ?string}
     */
    public function getAfricasTalkingCredentials(int $landlordId): array;

    /**
     * Get WhatsApp number for a landlord.
     */
    public function getWhatsAppNumber(int $landlordId): ?string;

    /**
     * Get WhatsApp template SID for a specific notification type.
     */
    public function getWhatsAppTemplateSid(int $landlordId, string $type): ?string;

    /**
     * Get rate limits for notification sending.
     *
     * @return array{hourly: int, daily: int}
     */
    public function getRateLimits(int $landlordId): array;

    /**
     * Set the SMS provider for a landlord.
     *
     * @param  string  $provider  'none', 'twilio', or 'africas_talking'
     */
    public function setSmsProvider(int $landlordId, string $provider): void;

    /**
     * Set Twilio credentials (dual-write to both legacy and new tables).
     *
     * @param  array{account_sid: string, auth_token: string, phone_number: string}  $credentials
     */
    public function setTwilioCredentials(int $landlordId, array $credentials): void;

    /**
     * Set Africa's Talking credentials (dual-write to both legacy and new tables).
     *
     * @param  array{api_key: string, username: string, from?: string}  $credentials
     */
    public function setAfricasTalkingCredentials(int $landlordId, array $credentials): void;

    /**
     * Set WhatsApp number (dual-write to both legacy and new tables).
     */
    public function setWhatsAppNumber(int $landlordId, string $number): void;

    /**
     * Set WhatsApp template SID for a notification type (dual-write).
     */
    public function setWhatsAppTemplateSid(int $landlordId, string $type, string $sid): void;
}
