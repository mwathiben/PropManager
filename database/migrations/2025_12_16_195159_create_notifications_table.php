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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('cascade'); // Null for bulk notifications
            $table->enum('type', [
                'rent_reminder',
                'arrears_notice',
                'invoice',
                'receipt',
                'rent_hike',
                'lease_expiry',
                'lease_renewal',
                'maintenance_notice',
                'general',
            ]);
            $table->enum('channel', ['email', 'sms', 'whatsapp']);
            $table->string('subject')->nullable(); // For email
            $table->text('message');
            $table->json('data')->nullable(); // Template variables, attachments, etc.
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered', 'read'])->default('pending');
            $table->string('external_id')->nullable(); // Provider's message ID (Twilio, etc.)
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['landlord_id', 'recipient_id', 'status']);
            $table->index(['landlord_id', 'type', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
