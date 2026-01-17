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
        Schema::create('tenant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('twilio_message_sid')->unique();
            $table->string('from_number');
            $table->text('body');
            $table->json('media_urls')->nullable();
            $table->string('source')->default('whatsapp');
            $table->string('status')->default('received');
            $table->string('action_type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('from_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_messages');
    }
};
