<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_amount_cents')->nullable()->after('total');
            $table->unsignedInteger('tax_rate_bps')->nullable()->after('tax_amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['tax_amount_cents', 'tax_rate_bps']);
        });
    }
};
