<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->boolean('intasend_enabled')->default(false)->after('paystack_enabled');
            $table->string('intasend_publishable_key')->nullable()->after('intasend_enabled');
            $table->text('intasend_secret_key')->nullable()->after('intasend_publishable_key');
            $table->string('intasend_webhook_challenge')->nullable()->after('intasend_secret_key');
            $table->enum('intasend_environment', ['sandbox', 'production'])->default('sandbox')->after('intasend_webhook_challenge');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'intasend_enabled',
                'intasend_publishable_key',
                'intasend_secret_key',
                'intasend_webhook_challenge',
                'intasend_environment',
            ]);
        });
    }
};
