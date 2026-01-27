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
        Schema::table('move_out_deductions', function (Blueprint $table) {
            $table->boolean('auto_applied')->default(false)->after('photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('move_out_deductions', function (Blueprint $table) {
            $table->dropColumn('auto_applied');
        });
    }
};
