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
        Schema::table('notification_preferences', function (Blueprint $table) {
            // Add in_app channel preference (default enabled - always show in-app notifications)
            $table->boolean('in_app_enabled')->default(true)->after('push_enabled');

            // Add invitation type preferences (default enabled)
            $table->boolean('caretaker_invitation_enabled')->default(true)->after('general_enabled');
            $table->boolean('tenant_invitation_enabled')->default(true)->after('caretaker_invitation_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'in_app_enabled',
                'caretaker_invitation_enabled',
                'tenant_invitation_enabled',
            ]);
        });
    }
};
