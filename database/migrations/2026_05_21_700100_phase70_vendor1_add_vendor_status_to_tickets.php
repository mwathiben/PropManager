<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-70 VENDOR-PORTAL: a vendor's response to an assignment. NULL =
 * not yet assigned to a vendor; pending = assigned, awaiting the
 * vendor's accept/decline; accepted/declined = their response. Foundation
 * for the portal dashboard + ticket inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('vendor_status', ['pending', 'accepted', 'declined'])->nullable()->after('vendor_id');
            $table->timestamp('vendor_responded_at')->nullable()->after('vendor_status');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['vendor_status', 'vendor_responded_at']);
        });
    }
};
