<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', [
                'rent_reminder',
                'arrears_notice',
                'lease_expiry',
            ]);
            $table->enum('trigger', [
                'days_before_due',
                'days_after_overdue',
                'days_before_expiry',
            ]);
            $table->unsignedInteger('days_offset')->default(7);
            $table->time('send_time')->default('09:00:00');
            $table->json('channels');
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'is_active']);
            $table->index(['type', 'trigger', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_schedules');
    }
};
