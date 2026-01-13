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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');

            // Notification type preferences
            $table->boolean('rent_reminder_enabled')->default(true);
            $table->boolean('arrears_notice_enabled')->default(true);
            $table->boolean('invoice_enabled')->default(true);
            $table->boolean('receipt_enabled')->default(true);
            $table->boolean('rent_hike_enabled')->default(true);
            $table->boolean('lease_expiry_enabled')->default(true);
            $table->boolean('lease_renewal_enabled')->default(true);
            $table->boolean('maintenance_notice_enabled')->default(true);
            $table->boolean('general_enabled')->default(true);

            // Channel preferences
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false); // Default off due to cost
            $table->boolean('whatsapp_enabled')->default(false);

            // Reminder timing preferences
            $table->integer('rent_reminder_days_before')->default(7); // Days before rent due
            $table->string('preferred_time')->default('09:00'); // Time of day for notifications

            // Contact information
            $table->string('whatsapp_number')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'landlord_id']);
            $table->index('landlord_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
