<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-49 VENDOR-MARKETPLACE-1: link tickets to external contractors.
 *
 * tickets.assigned_to (User FK) stays as the in-house owner (caretaker
 * who oversees the work); vendor_id (Vendor FK) is the contractor
 * actually doing the work. The two are not mutually exclusive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('assigned_to')
                ->constrained('vendors')->nullOnDelete();
            $table->index('vendor_id', 'tickets_vendor_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex('tickets_vendor_id_idx');
            $table->dropColumn('vendor_id');
        });
    }
};
