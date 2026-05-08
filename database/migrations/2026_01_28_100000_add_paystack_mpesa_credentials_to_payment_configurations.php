<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->string('paystack_public_key')->nullable()->after('paystack_enabled');
            $table->text('paystack_secret_key')->nullable()->after('paystack_public_key');
            $table->text('mpesa_consumer_key')->nullable()->after('mpesa_passkey');
            $table->text('mpesa_consumer_secret')->nullable()->after('mpesa_consumer_key');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'paystack_public_key',
                'paystack_secret_key',
                'mpesa_consumer_key',
                'mpesa_consumer_secret',
            ]);
        });
    }
};
