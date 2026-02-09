<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;

class VoidPaymentResult
{
    public function __construct(
        public readonly Payment $payment,
        public readonly ?Invoice $invoice,
        public readonly ?InvoiceStatus $previousInvoiceStatus,
        public readonly ?InvoiceStatus $newInvoiceStatus,
    ) {}

    public function successMessage(): string
    {
        return 'Payment voided successfully.';
    }

    public function invoiceWasRecalculated(): bool
    {
        return $this->invoice !== null;
    }
}
