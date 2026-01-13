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
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->string('fiscal_year_type', 20)->default('calendar')->after('receipt_thank_you_message');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1)->after('fiscal_year_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->dropColumn(['fiscal_year_type', 'fiscal_year_start_month']);
        });
    }
};
