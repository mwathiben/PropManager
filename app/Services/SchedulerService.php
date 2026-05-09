<?php

namespace App\Services;

use App\Jobs\SendScheduledNotificationsJob;
use App\Models\Lease;
use App\Models\NotificationSchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SchedulerService
{
    protected NotificationService $notificationService;

    protected TemplateService $templateService;

    public function __construct(
        NotificationService $notificationService,
        TemplateService $templateService
    ) {
        $this->notificationService = $notificationService;
        $this->templateService = $templateService;
    }

    /**
     * Process all active schedules that should run now
     */
    public function processSchedules(): array
    {
        $results = [
            'processed' => 0,
            'notifications_sent' => 0,
            'errors' => [],
        ];

        // Get all active schedules
        $schedules = NotificationSchedule::active()->get();

        foreach ($schedules as $schedule) {
            if (! $schedule->shouldRunNow()) {
                continue;
            }

            try {
                $count = $this->processSchedule($schedule);
                $results['processed']++;
                $results['notifications_sent'] += $count;
                $schedule->markAsRun();
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ];
                Log::error('Schedule processing failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Process a single schedule
     */
    public function processSchedule(NotificationSchedule $schedule): int
    {
        return match ($schedule->type) {
            'rent_reminder' => $this->processRentReminders($schedule),
            'arrears_notice' => $this->processArrearsNotices($schedule),
            'lease_expiry' => $this->processLeaseExpiryReminders($schedule),
            default => 0,
        };
    }

    /**
     * Process rent reminder schedule
     */
    public function processRentReminders(NotificationSchedule $schedule): int
    {
        $nextDueDate = now()->startOfMonth()->addMonth();
        $daysUntilDue = (int) now()->startOfDay()->diffInDays($nextDueDate);

        if ($daysUntilDue !== $schedule->days_offset) {
            return 0;
        }

        $tenants = $this->getEligibleTenants($schedule);
        $count = 0;

        foreach ($tenants as $tenant) {
            // Eager-loaded above (PERF-P5): avoid the per-tenant lazy query.
            $lease = $tenant->leases->first();
            if (! $lease) {
                continue;
            }

            $context = $this->templateService->buildTenantContext($tenant, $lease, [
                'due_date' => $nextDueDate->format('F j, Y'),
                'days_until_due' => $schedule->days_offset,
            ]);

            // Get subject and message
            $subject = 'Rent Reminder';
            $message = "Your rent of {$context['currency_symbol']} {$context['rent_amount']} is due in {$schedule->days_offset} days.";

            if ($schedule->template) {
                $rendered = $schedule->template->renderRaw($context);
                $subject = $rendered['subject'];
                $message = $rendered['body'];
            }

            // Queue the notification
            SendScheduledNotificationsJob::dispatch(
                $schedule,
                $tenant->id,
                $subject,
                $message,
                $context
            );

            $count++;
        }

        return $count;
    }

    /**
     * Process arrears notice schedule
     */
    public function processArrearsNotices(NotificationSchedule $schedule): int
    {
        $tenants = $this->getTenatsWithArrears($schedule);
        $count = 0;

        foreach ($tenants as $tenant) {
            // Eager-loaded above (PERF-P6): avoid the per-tenant lazy query.
            $lease = $tenant->leases->first();

            if (! $lease) {
                continue;
            }

            // Calculate days overdue (simplified - you may want more complex logic)
            $daysOverdue = now()->day; // Days past the 1st of the month

            if ($daysOverdue < $schedule->days_offset) {
                continue;
            }

            $context = $this->templateService->buildTenantContext($tenant, $lease, [
                'days_overdue' => $daysOverdue,
                'last_payment_date' => 'N/A', // You'd get this from payment history
            ]);

            $subject = 'Payment Overdue - Arrears Notice';
            $message = "You have an outstanding balance of {$context['currency_symbol']} {$context['arrears_amount']} which is {$daysOverdue} days overdue.";

            if ($schedule->template) {
                $rendered = $schedule->template->renderRaw($context);
                $subject = $rendered['subject'];
                $message = $rendered['body'];
            }

            SendScheduledNotificationsJob::dispatch(
                $schedule,
                $tenant->id,
                $subject,
                $message,
                $context
            );

            $count++;
        }

        return $count;
    }

    /**
     * Process lease expiry reminder schedule
     */
    public function processLeaseExpiryReminders(NotificationSchedule $schedule): int
    {
        $targetDate = now()->addDays($schedule->days_offset)->toDateString();

        $expiringLeases = Lease::where('landlord_id', $schedule->landlord_id)
            ->where('is_active', true)
            ->whereDate('end_date', $targetDate)
            ->with(['tenant', 'unit.building.property'])
            ->get();

        $count = 0;

        foreach ($expiringLeases as $lease) {
            if (! $lease->tenant) {
                continue;
            }

            $context = $this->templateService->buildTenantContext($lease->tenant, $lease, [
                'expiry_date' => $lease->end_date->format('F j, Y'),
                'days_until_expiry' => $schedule->days_offset,
            ]);

            $subject = 'Lease Expiry Reminder';
            $message = "Your lease will expire on {$context['expiry_date']} ({$schedule->days_offset} days from now).";

            if ($schedule->template) {
                $rendered = $schedule->template->renderRaw($context);
                $subject = $rendered['subject'];
                $message = $rendered['body'];
            }

            SendScheduledNotificationsJob::dispatch(
                $schedule,
                $lease->tenant->id,
                $subject,
                $message,
                $context
            );

            $count++;
        }

        return $count;
    }

    /**
     * Get tenants eligible for notifications based on schedule
     */
    public function getEligibleTenants(NotificationSchedule $schedule): Collection
    {
        return User::where('role', 'tenant')
            ->where('landlord_id', $schedule->landlord_id)
            ->whereHas('leases', function ($query) {
                $query->where('is_active', true);
            })
            ->with(['leases' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();
    }

    /**
     * Get tenants with arrears
     */
    protected function getTenatsWithArrears(NotificationSchedule $schedule): Collection
    {
        return User::where('role', 'tenant')
            ->where('landlord_id', $schedule->landlord_id)
            ->whereHas('leases', function ($query) {
                $query->where('is_active', true)
                    ->where('arrears', '>', 0);
            })
            ->with(['leases' => function ($query) {
                $query->where('is_active', true)
                    ->where('arrears', '>', 0);
            }])
            ->get();
    }

    /**
     * Run a schedule immediately (for manual trigger)
     */
    public function runNow(NotificationSchedule $schedule): int
    {
        $count = $this->processSchedule($schedule);
        $schedule->markAsRun();

        return $count;
    }

    /**
     * Get schedule statistics for a landlord
     */
    public function getStats(int $landlordId): array
    {
        $schedules = NotificationSchedule::where('landlord_id', $landlordId)->get();

        return [
            'total' => $schedules->count(),
            'active' => $schedules->where('is_active', true)->count(),
            'by_type' => $schedules->groupBy('type')->map->count(),
            'last_run' => $schedules->max('last_run_at'),
        ];
    }
}
