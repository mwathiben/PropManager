<?php

declare(strict_types=1);

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-45 EMERGENCY-CONTACT-SMS-2/3: verification columns +
 * source-of-truth resolution between emergency_contacts and the
 * users.emergency_contact_* mirror.
 *
 *  - verified_at: timestamp when the contact's phone was SMS-verified.
 *  - verification_attempts_24h: rolling counter for rate-limit guard.
 *  - last_otp_sent_at: cool-down anchor for rate-limit.
 *
 * Backfill: walk every tenant with at least one emergency_contacts row;
 * write the is_primary=true row (or oldest if none flagged) into
 * users.emergency_contact_* so the mirror reflects the canonical row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_contacts', function (Blueprint $table): void {
            $table->timestamp('verified_at')
                ->nullable()
                ->after('is_primary');
            $table->unsignedTinyInteger('verification_attempts_24h')
                ->default(0)
                ->after('verified_at');
            $table->timestamp('last_otp_sent_at')
                ->nullable()
                ->after('verification_attempts_24h');
        });

        // Backfill users.emergency_contact_* from the canonical
        // emergency_contacts row for each tenant.
        $tenantIds = EmergencyContact::query()->distinct()->pluck('tenant_id');
        foreach ($tenantIds as $tenantId) {
            $row = EmergencyContact::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first();

            if ($row === null) {
                continue;
            }

            User::query()->where('id', $tenantId)->update([
                'emergency_contact_name' => $row->name,
                'emergency_contact_phone' => $row->phone,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('emergency_contacts', function (Blueprint $table): void {
            $table->dropColumn(['verified_at', 'verification_attempts_24h', 'last_otp_sent_at']);
        });
    }
};
