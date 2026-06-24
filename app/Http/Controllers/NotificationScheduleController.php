<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\StoreNotificationScheduleRequest;
use App\Http\Requests\Notification\UpdateNotificationScheduleRequest;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Services\SchedulerService;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notification schedule CRUD, split out of NotificationsController (M2
 * decomposition). Verbatim move of the schedule actions; routes keep their
 * original names (notifications.schedules.*) and point here. Behaviour is
 * locked by NotificationScheduleControllerTest.
 */
class NotificationScheduleController extends Controller
{
    use HasBuildingFilter;

    public function __construct(
        protected SchedulerService $schedulerService,
    ) {}

    public function schedules(Request $request): Response
    {
        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();

        $schedules = NotificationSchedule::where('landlord_id', $landlordId)
            ->with('template:id,name')
            ->orderBy('type')
            ->get()
            ->map(function ($schedule) {
                $schedule->trigger_description = $schedule->trigger_description;
                $schedule->next_run = $schedule->next_run;

                return $schedule;
            });

        $templates = NotificationTemplate::where('landlord_id', $landlordId)
            ->active()
            ->get(['id', 'name', 'type']);

        $scheduleTypes = [
            ['value' => 'rent_reminder', 'label' => 'Rent Reminder'],
            ['value' => 'arrears_notice', 'label' => 'Arrears Notice'],
            ['value' => 'lease_expiry', 'label' => 'Lease Expiry'],
        ];

        return Inertia::render('Notifications/Index', [
            'activeTab' => 'scheduled',
            'schedules' => $schedules,
            'templates' => $templates,
            'scheduleTypes' => $scheduleTypes,
            'buildings' => $this->getBuildingsForFilter(),
            'tenants' => [],
            'notifications' => ['data' => []],
            'filters' => [],
        ]);
    }

    public function storeSchedule(StoreNotificationScheduleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();

        NotificationSchedule::create([
            'landlord_id' => $landlordId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'trigger' => $validated['trigger'],
            'days_offset' => $validated['days_offset'],
            'send_time' => $validated['send_time'],
            'channels' => $validated['channels'],
            'template_id' => $validated['template_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Schedule created successfully.');
    }

    public function updateSchedule(NotificationSchedule $schedule, UpdateNotificationScheduleRequest $request): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $validated = $request->validated();

        $schedule->update($validated);

        return redirect()->back()->with('success', 'Schedule updated successfully.');
    }

    public function toggleSchedule(NotificationSchedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $schedule->update(['is_active' => ! $schedule->is_active]);

        $status = $schedule->is_active ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "Schedule {$status} successfully.");
    }

    public function destroySchedule(NotificationSchedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $schedule->delete();

        return redirect()->back()->with('success', 'Schedule deleted successfully.');
    }

    public function runScheduleNow(NotificationSchedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $count = $this->schedulerService->runNow($schedule);

        return redirect()->back()->with('success', "Schedule executed. {$count} notifications queued.");
    }

    private function authorizeSchedule(NotificationSchedule $schedule): void
    {
        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();

        if ($schedule->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized');
        }
    }
}
