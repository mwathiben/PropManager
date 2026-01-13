<?php

namespace App\Services\Banking;

use DateTime;

interface BankServiceInterface
{
    public function validateWebhook(string $signature, string $payload): bool;

    public function parsePaymentNotification(array $payload): PaymentNotification;

    public function getTransactionHistory(string $accountNumber, DateTime $from, DateTime $to): array;

    public function verifyAccountNumber(string $accountNumber): ?array;
}
