<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-67 ATTACHMENT-SCAN-2: per-document malware-scan verdict. Inbox
 * attachments are scanned before persistence; this records the outcome
 * (pending until scanned, then clean/infected/error). Infected files are
 * never persisted, so in practice rows are clean or, under fail-open,
 * error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'error'])
                ->default('pending')
                ->after('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('scan_status');
        });
    }
};
