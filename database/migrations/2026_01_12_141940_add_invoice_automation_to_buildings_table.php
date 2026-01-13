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
        Schema::table('buildings', function (Blueprint $table) {
            $table->boolean('auto_generate_invoices')->default(false);
            $table->unsignedTinyInteger('invoice_generation_day')->default(1);
            $table->boolean('auto_send_invoices')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['auto_generate_invoices', 'invoice_generation_day', 'auto_send_invoices']);
        });
    }
};
