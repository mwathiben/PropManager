<?php

declare(strict_types=1);

namespace App\Contracts;

interface SmsServiceInterface
{
    /**
     * Send an SMS message via the landlord's configured provider.
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function send(int $landlordId, string $phoneNumber, string $message): array;
}
