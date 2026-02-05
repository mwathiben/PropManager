<?php

declare(strict_types=1);

namespace App\Services\Payment;

class BulkPaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $successCount,
        public readonly int $failedCount,
        public readonly float $totalAmount,
        public readonly array $errors = [],
        public readonly int $archivedTenantsCreated = 0,
    ) {}

    public static function succeeded(
        int $successCount,
        float $totalAmount,
        int $archivedTenantsCreated = 0,
    ): self {
        return new self(
            success: true,
            successCount: $successCount,
            failedCount: 0,
            totalAmount: $totalAmount,
            archivedTenantsCreated: $archivedTenantsCreated,
        );
    }

    public static function partial(
        int $successCount,
        int $failedCount,
        float $totalAmount,
        array $errors,
        int $archivedTenantsCreated = 0,
    ): self {
        return new self(
            success: $successCount > 0,
            successCount: $successCount,
            failedCount: $failedCount,
            totalAmount: $totalAmount,
            errors: $errors,
            archivedTenantsCreated: $archivedTenantsCreated,
        );
    }

    public static function failed(int $paymentCount, string $errorMessage): self
    {
        return new self(
            success: false,
            successCount: 0,
            failedCount: $paymentCount,
            totalAmount: 0,
            errors: [['error' => $errorMessage]],
        );
    }

    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'success_count' => $this->successCount,
            'failed_count' => $this->failedCount,
            'total_amount' => $this->totalAmount,
            'errors' => $this->errors,
        ];

        if ($this->archivedTenantsCreated > 0) {
            $data['archived_tenants_created'] = $this->archivedTenantsCreated;
        }

        return $data;
    }
}
