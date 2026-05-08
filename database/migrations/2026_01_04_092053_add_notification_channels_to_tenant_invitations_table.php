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
        Schema::table('tenant_invitations', function (Blueprint $table) {
            // Notification channels (email, sms, whatsapp)
            $table->json('notification_channels')->nullable()->after('tenant_phone');

            // Delivery tracking timestamps
            $table->timestamp('email_sent_at')->nullable()->after('notification_channels');
            $table->timestamp('sms_sent_at')->nullable()->after('email_sent_at');
            $table->timestamp('whatsapp_sent_at')->nullable()->after('sms_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_invitations', function (Blueprint $table) {
            $table->dropColumn([
                'notification_channels',
                'email_sent_at',
                'sms_sent_at',
                'whatsapp_sent_at',
            ]);
        });
    }
};
