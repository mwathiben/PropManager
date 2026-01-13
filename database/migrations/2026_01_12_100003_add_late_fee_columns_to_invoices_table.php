<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('late_fees_total', 10, 2)->default(0)->after('arrears');
            $table->decimal('late_fees_waived', 10, 2)->default(0)->after('late_fees_total');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['late_fees_total', 'late_fees_waived']);
        });
    }
};
