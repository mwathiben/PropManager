<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_defaults', function (Blueprint $table) {
            $table->boolean('quiet_hours_queue_notifications')->default(true)->after('quiet_hours_end');
            $table->unsignedTinyInteger('max_retries')->default(3)->after('quiet_hours_queue_notifications');
            $table->unsignedTinyInteger('retry_delay_minutes')->default(5)->after('max_retries');
            $table->unsignedSmallInteger('daily_limit_per_tenant')->default(20)->after('retry_delay_minutes');
            $table->unsignedSmallInteger('hourly_limit_per_tenant')->default(5)->after('daily_limit_per_tenant');
            $table->string('sender_name')->nullable()->after('hourly_limit_per_tenant');
            $table->string('reply_to_email')->nullable()->after('sender_name');
            $table->unsignedSmallInteger('archive_days')->default(90)->after('reply_to_email');
            $table->boolean('track_read_status')->default(true)->after('archive_days');
        });
    }

    public function down(): void
    {
        Schema::table('notification_defaults', function (Blueprint $table) {
            $table->dropColumn([
                'quiet_hours_queue_notifications',
                'max_retries',
                'retry_delay_minutes',
                'daily_limit_per_tenant',
                'hourly_limit_per_tenant',
                'sender_name',
                'reply_to_email',
                'archive_days',
                'track_read_status',
            ]);
        });
    }
};
