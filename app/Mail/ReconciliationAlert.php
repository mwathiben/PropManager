<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ReconciliationReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReconciliationAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReconciliationReport $report
    ) {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment Reconciliation Alert - {$this->report->discrepancy_count} discrepancies found",
        );
    }

    public function content(): Content
    {
        $resultData = $this->report->result_data ?? [];

        $missingLocally = count(array_filter($resultData, fn ($d) => $d['type'] === 'missing_locally'));
        $missingRemotely = count(array_filter($resultData, fn ($d) => $d['type'] === 'missing_remotely'));
        $amountMismatches = count(array_filter($resultData, fn ($d) => $d['type'] === 'amount_mismatch'));

        return new Content(
            markdown: 'emails.reconciliation-alert',
            with: [
                'provider' => ucfirst($this->report->provider),
                'periodFrom' => $this->report->period_from->format('M d, Y'),
                'periodTo' => $this->report->period_to->format('M d, Y'),
                'discrepancyCount' => $this->report->discrepancy_count,
                'localCount' => $this->report->local_count,
                'remoteCount' => $this->report->remote_count,
                'matchedCount' => $this->report->matched_count,
                'missingLocally' => $missingLocally,
                'missingRemotely' => $missingRemotely,
                'amountMismatches' => $amountMismatches,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
