<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->boolean('prorate_first_month')->default(true);
            $table->boolean('include_last_month_rent')->default(false);
            $table->decimal('admin_fee_amount', 10, 2)->nullable();
            $table->decimal('key_deposit_amount', 10, 2)->nullable();
            $table->unsignedSmallInteger('first_invoice_due_days')->default(0);
            $table->boolean('auto_generate_first_invoice')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->dropColumn([
                'prorate_first_month',
                'include_last_month_rent',
                'admin_fee_amount',
                'key_deposit_amount',
                'first_invoice_due_days',
                'auto_generate_first_invoice',
            ]);
        });
    }
};
