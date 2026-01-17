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
            $table->timestamp('scheduled_for')->nullable()->after('read_at');
            $table->boolean('quiet_hours_suppressed')->default(false)->after('scheduled_for');

            $table->index('scheduled_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['scheduled_for']);
            $table->dropColumn(['scheduled_for', 'quiet_hours_suppressed']);
        });
    }
};
