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
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('fallback_channel', 20)->nullable()->after('channel');
            $table->timestamp('fallback_sent_at')->nullable()->after('fallback_channel');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('fallback_sent_at');
            $table->timestamp('timeout_at')->nullable()->after('retry_count');
            $table->timestamp('primary_attempt_at')->nullable()->after('timeout_at');

            $table->index(['status', 'timeout_at'], 'notifications_stuck_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_stuck_index');

            $table->dropColumn([
                'fallback_channel',
                'fallback_sent_at',
                'retry_count',
                'timeout_at',
                'primary_attempt_at',
            ]);
        });
    }
};
