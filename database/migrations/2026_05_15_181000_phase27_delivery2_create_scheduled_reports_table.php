<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-27 BI-DELIVERY-2: scheduled email delivery of saved reports.
 *
 * scheduled_reports rows wire a saved_report to a cadence + recipient.
 * The daily SendScheduledReports artisan command (routes/console.php)
 * finds rows where next_due_at <= now() and queues
 * ScheduledReportDelivery mailable with the rendered xlsx attached.
 *
 * cadence values: 'weekly', 'monthly', 'quarterly'. Adding a cadence
 * requires updating the ScheduledReport::CADENCES constant + the
 * next_due_at advancement switch in the command.
 *
 * recipient_email is the explicit delivery target. Phase-13 PERSONAL-DATA-1
 * compliance: the controller's validator (Phase27DeliveryTest) rejects
 * third-party emails — only the landlord's own + known caretaker addresses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_reports')) {
            return;
        }

        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('saved_report_id')->constrained('saved_reports')->cascadeOnDelete();
            $table->string('cadence', 32);
            $table->string('recipient_email', 200);
            $table->timestamp('next_due_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['next_due_at', 'landlord_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
