<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class PaymentQrCodeService
{
    public function generateReceiptQrCode(Payment $payment, array $options = []): string
    {
        $data = $this->buildReceiptQrData($payment);

        return $this->generateQrCodeSvg($data, $options);
    }

    public function generateInvoiceQrCode(Invoice $invoice, array $options = []): string
    {
        $data = $this->buildInvoiceQrData($invoice);

        return $this->generateQrCodeSvg($data, $options);
    }

    public function generatePaymentLinkQrCode(string $url, array $options = []): string
    {
        return $this->generateQrCodeSvg($url, $options);
    }

    protected function buildReceiptQrData(Payment $payment): string
    {
        $receipt = $payment->receipt;
        $invoice = $payment->invoice;
        $tenant = $payment->lease?->tenant;

        $lines = [
            'PAYMENT RECEIPT',
            'Receipt: '.($receipt?->receipt_number ?? $payment->reference),
            'Amount: '.($payment->currency ?? Currency::default())->symbol().' '.number_format($payment->amount, 2),
            'Date: '.$payment->payment_date?->format('Y-m-d'),
            'Method: '.ucfirst(str_replace('_', ' ', $payment->payment_method ?? 'N/A')),
        ];

        if ($payment->mpesa_transaction_id) {
            $lines[] = 'M-Pesa: '.$payment->mpesa_transaction_id;
        }

        if ($tenant) {
            $lines[] = 'Tenant: '.$tenant->name;
        }

        if ($invoice) {
            $lines[] = 'Invoice: '.$invoice->invoice_number;
        }

        return implode("\n", $lines);
    }

    protected function buildInvoiceQrData(Invoice $invoice): string
    {
        $tenant = $invoice->lease?->tenant;
        $unit = $invoice->lease?->unit;

        $lines = [
            'INVOICE',
            'No: '.$invoice->invoice_number,
            'Amount: '.($invoice->currency ?? Currency::default())->symbol().' '.number_format($invoice->total_due, 2),
            'Due: '.$invoice->due_date?->format('Y-m-d'),
            'Status: '.$invoice->status->label(),
        ];

        if ($tenant) {
            $lines[] = 'Tenant: '.$tenant->name;
        }

        if ($unit) {
            $lines[] = 'Unit: '.$unit->unit_number;
        }

        return implode("\n", $lines);
    }

    protected function generateQrCodeSvg(string $data, array $options = []): string
    {
        $size = $options['size'] ?? 200;
        $margin = $options['margin'] ?? 2;
        $primaryColor = $options['primary_color'] ?? '#000000';

        $rgb = $this->hexToRgb($primaryColor);

        $renderer = new ImageRenderer(
            new RendererStyle(
                $size,
                $margin,
                null,
                null,
                Fill::uniformColor(
                    new Rgb(255, 255, 255),
                    new Rgb($rgb['r'], $rgb['g'], $rgb['b'])
                )
            ),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($data);
    }

    public function generateBase64QrCode(string $data, array $options = []): string
    {
        $svg = $this->generateQrCodeSvg($data, $options);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
