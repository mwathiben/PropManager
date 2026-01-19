<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->enum('provider_type', ['email', 'sms', 'whatsapp', 'push']);
            $table->string('provider_name')->nullable();
            $table->text('credentials')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['landlord_id', 'provider_type']);
            $table->index('landlord_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_provider_configs');
    }
};
