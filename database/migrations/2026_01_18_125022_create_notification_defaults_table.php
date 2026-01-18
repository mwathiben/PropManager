<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('default_channels')->default('["email"]');
            $table->json('type_settings')->nullable();
            $table->unsignedTinyInteger('reminder_days_before_due')->default(7);
            $table->boolean('quiet_hours_enabled')->default(true);
            $table->time('quiet_hours_start')->default('22:00');
            $table->time('quiet_hours_end')->default('08:00');
            $table->timestamps();

            $table->index('landlord_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_defaults');
    }
};
