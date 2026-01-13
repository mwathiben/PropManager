<?php

namespace App\Services\Banking;

use DateTimeInterface;

class PaymentNotification
{
    public function __construct(
        public readonly string $bankCode,
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $accountNumber,
        public readonly ?string $reference,
        public readonly ?string $senderName,
        public readonly ?string $senderPhone,
        public readonly DateTimeInterface $transactionDate,
        public readonly array $rawPayload
    ) {}

    public function toArray(): array
    {
        return [
            'bank_code' => $this->bankCode,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'account_number' => $this->accountNumber,
            'reference' => $this->reference,
            'sender_name' => $this->senderName,
            'sender_phone' => $this->senderPhone,
            'transaction_date' => $this->transactionDate->format('Y-m-d H:i:s'),
        ];
    }
}
