<?php

declare(strict_types=1);

namespace App\Services\Sms\Contracts;

/**
 * Phase-45 EMERGENCY-CONTACT-SMS-1: SMS provider contract. Implementations
 * map to a concrete provider (Africa's Talking, Twilio, etc.).
 */
interface SmsDriver
{
    /**
     * Send a single SMS message. Returns a provider-side reference id
     * (or empty string if the provider doesn't return one).
     */
    public function send(string $phone, string $message): string;
}
