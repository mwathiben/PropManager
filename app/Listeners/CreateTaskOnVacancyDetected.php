<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\VacancyDetected;
use App\Models\LandlordTask;
use App\Models\Unit;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Phase-29 WF-VACANCY-2: when a vacancy is detected, create a
 * high-priority LandlordTask asking the landlord to list the unit.
 * Other future listeners (Phase-30 auto-marketing integration) can
 * subscribe to the same event without modifying this one.
 */
class CreateTaskOnVacancyDetected implements ShouldQueue
{
    public function handle(VacancyDetected $event): void
    {
        $unit = $event->unit;

        LandlordTask::create([
            'landlord_id' => $unit->landlord_id,
            'task_type' => 'list_unit',
            'related_to_id' => $unit->id,
            'related_to_type' => Unit::class,
            'title' => __('workflow.vacancy.task_title', ['number' => $unit->unit_number]),
            'description' => __('workflow.vacancy.task_description', ['number' => $unit->unit_number]),
            'priority' => 'high',
            'status' => LandlordTask::STATUS_PENDING,
            'due_date' => now()->addDays(7)->toDateString(),
            'source_workflow' => 'WF-VACANCY-2',
        ]);
    }
}
