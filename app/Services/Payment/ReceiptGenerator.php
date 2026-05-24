<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Mail\PaymentReceived;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptTemplate;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\Mail;

class ReceiptGenerator
{
    public function __construct(protected ReceiptService $receiptService) {}

    public function download(Payment $payment)
    {
        $payment->loadMissing(['invoice.lease.tenant', 'invoice.lease.unit.building', 'invoice.waterConnection.client', 'invoice.waterConnection.unit.building']);
        $receipt = $this->ensureReceipt($payment);

        return $this->receiptService->downloadPdf($receipt);
    }

    public function email(Payment $payment): void
    {
        $payment->loadMissing(['invoice.lease.tenant', 'invoice.lease.unit.building', 'invoice.waterConnection.client', 'invoice.waterConnection.unit.building']);

        // Phase-99: the payer is the lease's tenant OR the water connection's client.
        $recipient = $payment->invoice?->recipientUser();
        if (! $recipient?->email) {
            throw new \RuntimeException('Unable to send receipt - recipient not found.');
        }

        $receipt = $this->ensureReceipt($payment);

        // HANDLE-6: queue so an SMTP hiccup doesn't 500 the controller that
        // calls this from the request path.
        Mail::to($recipient->email)->queue(new PaymentReceived($payment, $payment->invoice));

        $receipt->markAsEmailed();
    }

    public function preview(InvoiceSetting $settings, ?ReceiptTemplate $template = null)
    {
        return $this->receiptService->streamPreviewPdf($settings, $template);
    }

    protected function ensureReceipt(Payment $payment): Receipt
    {
        return $payment->receipt ?? $this->receiptService->createReceipt($payment, $payment->invoice);
    }
}
