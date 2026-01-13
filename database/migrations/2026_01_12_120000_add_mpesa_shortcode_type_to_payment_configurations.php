<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->enum('mpesa_shortcode_type', ['paybill', 'till'])->default('paybill')->after('mpesa_account_name');
            $table->string('mpesa_shortcode', 20)->nullable()->after('mpesa_shortcode_type');
            $table->text('mpesa_passkey')->nullable()->after('mpesa_shortcode');
        });

        DB::table('payment_configurations')
            ->whereNotNull('mpesa_paybill')
            ->update([
                'mpesa_shortcode' => DB::raw('mpesa_paybill'),
            ]);

        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn('mpesa_paybill');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->string('mpesa_paybill')->nullable()->after('bank_branch');
        });

        DB::table('payment_configurations')
            ->whereNotNull('mpesa_shortcode')
            ->update([
                'mpesa_paybill' => DB::raw('mpesa_shortcode'),
            ]);

        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn(['mpesa_shortcode_type', 'mpesa_shortcode', 'mpesa_passkey']);
        });
    }
};
