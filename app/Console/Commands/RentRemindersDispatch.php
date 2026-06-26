<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\RentReminderPolicy;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-29 WF-RENT-REMIND-1: nightly tiered rent reminder dispatcher.
 *
 * Walks unpaid invoices (Sent/Partial/Overdue), resolves each lease's
 * reminder_tier → the landlord's matching RentReminderPolicy (falling
 * back to is_default=true when no exact match), computes
 * days_until_due = invoice.due_date - today() (negative when overdue),
 * and fires NotificationService::send when days_until_due matches one
 * of the policy's signed offsets.
 *
 * Idempotency: Cache::add keyed on invoice_id + offset for 60d so a
 * re-run on the same day never double-fires + a later run after the
 * lock expires can re-fire if the offset is matched again (e.g., a
 * cron skipped a day).
 *
 * NotificationService::send already consults NotificationPreference
 * (WF-RENT-REMIND-3) so a tenant who opted out of 'rent_reminder' type
 * silently receives nothing without us bypassing the preference check.
 */
class RentRemindersDispatch extends Command
{
    protected $signature = 'rent-reminders:dispatch {--dry-run}';

    protected $description = 'Phase-29 WF-RENT-REMIND-1: tiered rent reminder dispatcher.';

    private bool $dryRun;

    private NotificationService $notifications;

    public function handle(NotificationService $notifications, \App\Services\WorkflowLogger $workflowLogger): int
    {
        $this->notifications = $notifications;
        $this->dryRun = (bool) $this->option('dry-run');
        $today = CarbonImmutable::now()->startOfDay();
        $dispatched = 0;

        $invoices = Invoice::query()
            ->withoutGlobalScope('landlord')
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->whereNotNull('due_date')
            ->with(['lease.tenant'])
            ->get();

        foreach ($invoices as $invoice) {
            $dispatched += $this->remindForInvoice($invoice, $today);
        }

        $this->info("rent-reminders:dispatch: {$dispatched} reminder(s) dispatched".($this->dryRun ? ' (dry-run)' : ''));

        // Phase-30 INT-CI-1: WorkflowLogger silent-failure audit trail.
        $workflowLogger->log(
            workflowName: 'rent-reminders:dispatch',
            action: 'completed',
            metadata: ['dispatched' => $dispatched, 'dry_run' => $this->dryRun],
        );

        return self::SUCCESS;
    }

    private function remindForInvoice(Invoice $invoice, CarbonImmutable $today): int
    {
        $lease = $invoice->lease;
        if (! $lease || ! $lease->tenant) {
            return 0;
        }

        $policy = $this->resolvePolicy($invoice->landlord_id, $lease->reminder_tier);
        if (! $policy) {
            return 0;
        }

        $offsets = $policy->resolveOffsets();
        if ($offsets === []) {
            return 0;
        }

        $dueDate = CarbonImmutable::parse($invoice->due_date)->startOfDay();
        // diffInDays(today, dueDate, false): positive when due_date is in the
        // future. offset is relative to due_date: negative = days BEFORE due,
        // positive = days AFTER. Match: today + (-offset days) = due_date.
        $daysUntilDue = (int) $today->diffInDays($dueDate, false);

        $dispatched = 0;
        foreach ($offsets as $offset) {
            $dispatched += $this->dispatchForOffset($invoice, $lease, $offset, $daysUntilDue);
        }

        return $dispatched;
    }

    private function dispatchForOffset(Invoice $invoice, \App\Models\Lease $lease, int|string $offset, int $daysUntilDue): int
    {
        if ($daysUntilDue !== -(int) $offset) {
            return 0;
        }

        $key = sprintf('rent-reminder:%d:%d', $invoice->id, $offset);
        if (! Cache::add($key, true, now()->addDays(60))) {
            return 0;
        }

        if ($this->dryRun) {
            return 1;
        }

        $this->notifications->send(
            recipientId: $lease->tenant_id,
            type: 'rent_reminder',
            subject: __('workflow.rent_reminder.subject', [
                'number' => $invoice->invoice_number,
            ]),
            message: $this->messageForOffset((int) $offset, $invoice),
            data: [
                'invoice_id' => $invoice->id,
                'offset' => $offset,
                'days_until_due' => $daysUntilDue,
            ],
            landlordId: $invoice->landlord_id,
        );

        return 1;
    }

    private function resolvePolicy(int $landlordId, ?string $tier): ?RentReminderPolicy
    {
        $tier = $tier ?: 'standard';

        $exact = RentReminderPolicy::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('cadence_template', $tier)
            ->where('is_active', true)
            ->first();

        if ($exact) {
            return $exact;
        }

        return RentReminderPolicy::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    private function messageForOffset(int $offset, Invoice $invoice): string
    {
        $key = match (true) {
            $offset < 0 => 'workflow.rent_reminder.body_before',
            $offset === 0 => 'workflow.rent_reminder.body_due_today',
            default => 'workflow.rent_reminder.body_after',
        };

        return __($key, [
            'days' => abs($offset),
            'number' => $invoice->invoice_number,
            'amount' => number_format((float) $invoice->total_due - (float) $invoice->amount_paid, 2),
        ]);
    }
}
