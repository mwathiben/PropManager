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
            $table->string('deposit_status')->default('held');
            $table->decimal('deposit_refund_amount', 10, 2)->nullable();
            $table->decimal('deposit_deductions', 10, 2)->nullable();
            $table->text('deposit_deduction_reason')->nullable();
            $table->timestamp('deposit_processed_at')->nullable();
            $table->foreignId('deposit_processed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropForeign(['deposit_processed_by']);
            $table->dropColumn([
                'deposit_status',
                'deposit_refund_amount',
                'deposit_deductions',
                'deposit_deduction_reason',
                'deposit_processed_at',
                'deposit_processed_by',
            ]);
        });
    }
};
