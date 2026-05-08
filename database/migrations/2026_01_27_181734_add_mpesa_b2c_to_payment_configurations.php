<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->string('mpesa_b2c_shortcode')->nullable();
            $table->string('mpesa_b2c_initiator')->nullable();
            $table->text('mpesa_b2c_password')->nullable();
            $table->text('mpesa_b2c_security_credential')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'mpesa_b2c_shortcode',
                'mpesa_b2c_initiator',
                'mpesa_b2c_password',
                'mpesa_b2c_security_credential',
            ]);
        });
    }
};
