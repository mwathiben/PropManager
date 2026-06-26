<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\EvictionNoticeDraft;
use App\Models\Invoice;
use App\Models\LandlordTask;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-29 WF-LATE-FEE-1: nightly escalation chain for overdue invoices.
 *
 *   Day 5  overdue  → SMS-emphasis arrears reminder
 *   Day 10 overdue  → LandlordTask "Call tenant {name} re invoice {num}"
 *   Day 30 overdue  → EvictionNoticeDraft generated for landlord
 *                     review (NEVER auto-sent)
 *
 * Each level is idempotent via Cache::add keyed on invoice_id + level
 * for 60d. Runs at 00:30 — after invoices:mark-overdue (00:05) and
 * invoices:apply-late-fees (00:10) so the escalation operates on the
 * freshly-updated overdue corpus.
 */
class InvoicesEscalateOverdue extends Command
{
    protected $signature = 'invoices:escalate-overdue {--dry-run}';

    protected $description = 'Phase-29 WF-LATE-FEE-1: escalate chronically overdue invoices via SMS / task / eviction draft.';

    public const LEVEL_SMS = 'reminder_sms';

    public const LEVEL_TASK = 'create_task';

    public const LEVEL_DRAFT = 'draft_eviction_notice';

    public const LEVEL_BY_DAYS = [
        5 => self::LEVEL_SMS,
        10 => self::LEVEL_TASK,
        30 => self::LEVEL_DRAFT,
    ];

    private NotificationService $notifications;

    public function handle(NotificationService $notifications, \App\Services\WorkflowLogger $workflowLogger): int
    {
        $this->notifications = $notifications;
        $dryRun = (bool) $this->option('dry-run');
        $today = CarbonImmutable::now()->startOfDay();
        $counts = ['sms' => 0, 'task' => 0, 'draft' => 0];

        $invoices = Invoice::query()
            ->withoutGlobalScope('landlord')
            ->where('status', InvoiceStatus::Overdue)
            ->whereNotNull('due_date')
            ->with(['lease.tenant'])
            ->get();

        foreach ($invoices as $invoice) {
            $this->processInvoice($invoice, $today, $dryRun, $counts);
        }

        $this->info(sprintf(
            'invoices:escalate-overdue: %d SMS, %d task(s), %d draft(s)%s',
            $counts['sms'],
            $counts['task'],
            $counts['draft'],
            $dryRun ? ' (dry-run)' : '',
        ));

        $workflowLogger->log(
            workflowName: 'invoices:escalate-overdue',
            action: 'completed',
            metadata: $counts + ['dry_run' => $dryRun],
        );

        return self::SUCCESS;
    }

    private function processInvoice(Invoice $invoice, CarbonImmutable $today, bool $dryRun, array &$counts): void
    {
        $lease = $invoice->lease;
        if (! $lease) {
            return;
        }

        $daysOverdue = (int) CarbonImmutable::parse($invoice->due_date)->startOfDay()->diffInDays($today, false);

        if (! array_key_exists($daysOverdue, self::LEVEL_BY_DAYS)) {
            return;
        }
        $level = self::LEVEL_BY_DAYS[$daysOverdue];

        $key = sprintf('invoice-escalation:%d:%s', $invoice->id, $level);
        if (! Cache::add($key, true, now()->addDays(60))) {
            return;
        }

        if ($dryRun) {
            return;
        }

        match ($level) {
            self::LEVEL_SMS => $this->fireSmsReminder($this->notifications, $invoice, $counts),
            self::LEVEL_TASK => $this->createCallTask($invoice, $counts),
            self::LEVEL_DRAFT => $this->createEvictionDraft($invoice, $counts),
        };
    }

    private function fireSmsReminder(NotificationService $notifications, Invoice $invoice, array &$counts): void
    {
        $tenant = $invoice->lease->tenant;
        if (! $tenant) {
            return;
        }

        $notifications->send(
            recipientId: $tenant->id,
            type: 'arrears_notice',
            subject: __('workflow.late_fee.sms_subject', ['number' => $invoice->invoice_number]),
            message: __('workflow.late_fee.sms_body', [
                'number' => $invoice->invoice_number,
                'amount' => number_format((float) $invoice->total_due - (float) $invoice->amount_paid, 2),
            ]),
            data: ['invoice_id' => $invoice->id, 'level' => self::LEVEL_SMS],
            landlordId: $invoice->landlord_id,
        );
        $counts['sms']++;
    }

    private function createCallTask(Invoice $invoice, array &$counts): void
    {
        $tenant = $invoice->lease->tenant;

        LandlordTask::create([
            'landlord_id' => $invoice->landlord_id,
            'task_type' => 'overdue_invoice_call',
            'related_to_id' => $invoice->id,
            'related_to_type' => Invoice::class,
            'title' => __('workflow.late_fee.task_title', [
                'tenant' => $tenant?->name ?? 'tenant',
                'number' => $invoice->invoice_number,
            ]),
            'description' => __('workflow.late_fee.task_description', [
                'number' => $invoice->invoice_number,
            ]),
            'priority' => 'high',
            'status' => LandlordTask::STATUS_PENDING,
            'due_date' => now()->addDays(3)->toDateString(),
            'source_workflow' => 'WF-LATE-FEE-1',
        ]);
        $counts['task']++;
    }

    private function createEvictionDraft(Invoice $invoice, array &$counts): void
    {
        $lease = $invoice->lease;
        $tenant = $lease->tenant;
        if (! $tenant) {
            return;
        }

        $arrearsCents = (int) round(((float) $invoice->total_due - (float) $invoice->amount_paid) * 100);

        EvictionNoticeDraft::create([
            'landlord_id' => $invoice->landlord_id,
            'lease_id' => $lease->id,
            'tenant_id' => $tenant->id,
            'related_invoice_ids' => [$invoice->id],
            'total_arrears_cents' => $arrearsCents,
            'draft_body' => $this->draftBodyTemplate($invoice, $tenant->name, $arrearsCents),
            'status' => EvictionNoticeDraft::STATUS_DRAFT,
            'source_workflow' => 'WF-LATE-FEE-1',
        ]);
        $counts['draft']++;
    }

    private function draftBodyTemplate(Invoice $invoice, string $tenantName, int $arrearsCents): string
    {
        return __('workflow.late_fee.eviction_draft_body', [
            'tenant' => $tenantName,
            'number' => $invoice->invoice_number,
            'arrears' => number_format($arrearsCents / 100, 2),
            'due_date' => CarbonImmutable::parse($invoice->due_date)->toDateString(),
        ]);
    }
}
