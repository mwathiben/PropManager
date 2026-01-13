<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('late_fee_policy_id')->constrained()->restrictOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('fee_amount', 10, 2);
            $table->decimal('cumulative_total', 10, 2);
            $table->date('applied_date');
            $table->unsignedSmallInteger('days_overdue');

            $table->boolean('is_waived')->default(false);
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->string('waiver_reason')->nullable();

            $table->timestamps();

            $table->index(['invoice_id', 'applied_date']);
            $table->index(['landlord_id', 'applied_date']);
            $table->index(['invoice_id', 'is_waived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_fees');
    }
};
