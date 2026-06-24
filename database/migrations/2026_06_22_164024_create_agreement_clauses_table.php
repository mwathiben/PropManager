<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice-2 PR-2.1: a clause instance inside an agreement — the chosen clause plus
 * its filled params (e.g. the fee clause's {type, value, base, flat_cadence}).
 * The applicator (PR 2.3) reads the fee instance's params to write+lock
 * PropertyOwner.management_fee_*. clause_id is restrict-on-delete so a clause in
 * use can't vanish; scoped via the parent agreement (no own landlord_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_clauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clause_id')->constrained()->restrictOnDelete();
            $table->json('params')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['management_agreement_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_clauses');
    }
};
