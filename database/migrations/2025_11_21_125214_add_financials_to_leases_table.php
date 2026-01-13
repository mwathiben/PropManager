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
        Schema::table('leases', function (Blueprint $table) {
            $table->decimal('service_charge', 10, 2)->default(0)->after('rent_amount');
            $table->string('lease_agreement_file')->nullable()->after('is_active');
            $table->date('move_in_date')->nullable()->after('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            //
        });
    }
};
