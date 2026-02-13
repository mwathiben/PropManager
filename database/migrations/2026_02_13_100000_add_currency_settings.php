<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->string('default_currency', 3)->default('KES')->after('accepted_payment_methods');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('auto_send_invoices');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn('default_currency');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
