<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stripe_customer_id', 64);
            $table->string('default_payment_method_id', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('user_id', 'sc_user_id_unique');
            $table->unique('stripe_customer_id', 'sc_stripe_customer_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_customers');
    }
};
