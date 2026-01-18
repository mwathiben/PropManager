<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('uses_landlord_defaults')->default(true)->after('landlord_id');
            $table->timestamp('overridden_at')->nullable()->after('uses_landlord_defaults');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['uses_landlord_defaults', 'overridden_at']);
        });
    }
};
