<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice-2 PR-2.3: the drift-lock for the management fee. Once a signed agreement
 * is activated, AgreementApplicator writes the governed management_fee_* and
 * stamps management_fee_locked_at + the source management_agreement_id. While
 * locked, the fee is immutable except through the applicator (an amendment that
 * is re-signed) — enforced by the PropertyOwner saving guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            $table->timestamp('management_fee_locked_at')->nullable()->after('management_fee_flat_cadence');
            $table->foreignId('management_agreement_id')->nullable()->after('management_fee_locked_at')
                ->constrained('management_agreements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('management_agreement_id');
            $table->dropColumn('management_fee_locked_at');
        });
    }
};
