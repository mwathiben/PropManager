<?php

namespace App\Mail;

use App\Enums\Currency;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class InvoiceReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        $isOverdue = $this->invoice->due_date->isPast();
        $key = $isOverdue ? 'emails.subjects.invoice_overdue' : 'emails.subjects.invoice_reminder';

        return new Envelope(
            subject: __($key, ['number' => $this->invoice->invoice_number]),
        );
    }

    public function content(): Content
    {
        $this->invoice->load(['lease.tenant', 'lease.unit.building.property']);

        $tenant = $this->invoice->lease->tenant;
        $unit = $this->invoice->lease->unit;
        $building = $unit->building;
        $property = $building->property;

        $balance = $this->invoice->total_due - $this->invoice->amount_paid;
        $isOverdue = $this->invoice->due_date->isPast();
        $daysOverdue = $isOverdue ? $this->invoice->due_date->diffInDays(now()) : 0;

        return new Content(
            markdown: 'emails.invoice-reminder',
            with: [
                'invoice' => $this->invoice,
                'tenant' => $tenant,
                'invoiceNumber' => $this->invoice->invoice_number,
                'billingPeriod' => $this->invoice->billing_period->translatedFormat('F Y'),
                'totalDue' => number_format($this->invoice->total_due, 2),
                'amountPaid' => number_format($this->invoice->amount_paid, 2),
                'balance' => number_format($balance, 2),
                'dueDate' => $this->invoice->due_date->translatedFormat('F d, Y'),
                'isOverdue' => $isOverdue,
                'daysOverdue' => $daysOverdue,
                'propertyName' => $property->name,
                'buildingName' => $building->name,
                'unitNumber' => $unit->unit_number,
                'invoiceUrl' => route('invoices.show', $this->invoice),
                'currency_symbol' => ($this->invoice->currency ?? Currency::default())->symbol(),
                'unsubscribeUrl' => URL::temporarySignedRoute(
                    'email.preferences',
                    now()->addDays(30),
                    ['user' => $tenant->id]
                ),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
