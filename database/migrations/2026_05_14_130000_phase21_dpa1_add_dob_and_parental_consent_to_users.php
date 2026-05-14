<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-21 DEFER-DPA-1 (closes Phase-13 DPA-10 deferral):
 * Article 8 of Kenya DPA — children's data special handling. Phase-13
 * shipped KenyaDpaService::isMinor() + the policy doc but the schema
 * never landed, leaving the gate function dead code. This migration
 * adds the three columns the gate needs:
 *   - dob: nullable date. Backfill NULL — operator process resolves.
 *   - parental_consent_artefact_url: nullable string(512). Off-platform
 *     storage (Google Drive, S3, scanned ID) URL pointing to signed
 *     parental consent. Required when dob says minor.
 *   - parental_consent_provided_at: nullable timestamp. Set when the
 *     landlord uploads + confirms the artefact.
 *
 * Index on (dob) supports future operator-side minor-tenant audit
 * queries (e.g. nightly check that all tenants with dob < 18yr have
 * parental_consent_provided_at). Non-unique, nullable-friendly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('dob')->nullable()->after('national_id');
            $table->string('parental_consent_artefact_url', 512)->nullable()->after('dob');
            $table->timestamp('parental_consent_provided_at')->nullable()->after('parental_consent_artefact_url');
            $table->index('dob', 'users_dob_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_dob_idx');
            $table->dropColumn(['dob', 'parental_consent_artefact_url', 'parental_consent_provided_at']);
        });
    }
};
