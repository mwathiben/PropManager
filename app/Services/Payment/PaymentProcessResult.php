<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Models\Payment;

/**
 * Result object for payment processing operations.
 */
class PaymentProcessResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_ALREADY_PROCESSED = 'already_processed';

    public const STATUS_INVOICE_NOT_FOUND = 'invoice_not_found';

    public const STATUS_ERROR = 'error';

    private function __construct(
        public readonly string $status,
        public readonly ?Payment $payment = null,
        public readonly ?Invoice $invoice = null,
        public readonly float $overpayment = 0,
        public readonly array $pendingOverpayments = [],
        public readonly ?string $errorMessage = null
    ) {}

    public static function success(
        Payment $payment,
        Invoice $invoice,
        float $overpayment = 0,
        array $pendingOverpayments = []
    ): self {
        return new self(
            status: self::STATUS_SUCCESS,
            payment: $payment,
            invoice: $invoice,
            overpayment: $overpayment,
            pendingOverpayments: $pendingOverpayments
        );
    }

    public static function alreadyProcessed(?Payment $existingPayment): self
    {
        return new self(
            status: self::STATUS_ALREADY_PROCESSED,
            payment: $existingPayment
        );
    }

    public static function invoiceNotFound(): self
    {
        return new self(
            status: self::STATUS_INVOICE_NOT_FOUND,
            errorMessage: 'Invoice not found'
        );
    }

    public static function error(string $message): self
    {
        return new self(
            status: self::STATUS_ERROR,
            errorMessage: $message
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isAlreadyProcessed(): bool
    {
        return $this->status === self::STATUS_ALREADY_PROCESSED;
    }

    public function isInvoiceNotFound(): bool
    {
        return $this->status === self::STATUS_INVOICE_NOT_FOUND;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function hasOverpayment(): bool
    {
        return $this->overpayment > 0;
    }

    /**
     * Get a formatted success message.
     */
    public function getSuccessMessage(): string
    {
        if (! $this->payment instanceof Payment) {
            return '';
        }

        $message = 'Payment of KES '.number_format($this->payment->amount, 2).' successful!';

        if ($this->hasOverpayment() && $this->overpayment > 0) {
            $message .= ' KES '.number_format($this->overpayment, 2).' credited to wallet.';
        }

        return $message;
    }
}
