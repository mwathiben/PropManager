<?php

namespace App\Mail;

use App\Enums\Currency;
use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DepositRefundNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lease $lease,
        public string $type
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'refunded' => 'Your Security Deposit Has Been Refunded',
            'partial_refund' => 'Your Security Deposit Has Been Partially Refunded',
            'forfeited' => 'Security Deposit Forfeiture Notice',
            default => 'Security Deposit Update',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.deposit-refund',
            with: [
                'lease' => $this->lease,
                'tenant' => $this->lease->tenant,
                'unit' => $this->lease->unit,
                'type' => $this->type,
                'depositAmount' => $this->lease->deposit_amount,
                'refundAmount' => $this->lease->deposit_refund_amount,
                'deductions' => $this->lease->deposit_deductions,
                'deductionReason' => $this->lease->deposit_deduction_reason,
                'currency_symbol' => ($this->lease->unit->building?->getEffectiveCurrency() ?? Currency::default())->symbol(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
