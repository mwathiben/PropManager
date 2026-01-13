<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_credit')->default(false);
            $table->timestamps();
        });

        DB::table('invoice_types')->insert([
            [
                'code' => 'standard',
                'name' => 'Standard Invoice',
                'description' => 'Regular monthly rent invoice',
                'is_system' => true,
                'is_credit' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'first_payment',
                'name' => 'First Payment Invoice',
                'description' => 'Initial invoice for new tenants including deposit and first rent',
                'is_system' => true,
                'is_credit' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'utility',
                'name' => 'Utility Invoice',
                'description' => 'Invoice for utility charges only (water, electricity)',
                'is_system' => true,
                'is_credit' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'arrears',
                'name' => 'Arrears Invoice',
                'description' => 'Invoice for outstanding balance collection',
                'is_system' => true,
                'is_credit' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'credit_note',
                'name' => 'Credit Note',
                'description' => 'Credit applied against existing invoices',
                'is_system' => true,
                'is_credit' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_types');
    }
};
