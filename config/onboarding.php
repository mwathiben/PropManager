<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Mirror Registry
    |--------------------------------------------------------------------------
    |
    | Phase-46 CANONICAL-AUDIT-1: declare every users.* column that
    | denormalises a child-table record. The Mirror Registry is read by:
    |   - MirrorAuditService (joins users to the canonical table, diffs the column)
    |   - onboarding:dedupe-audit cron (emits canonical_mirror_drift_count gauge)
    |   - Phase46OnboardingMirrorRegistryTest (asserts no new users.* mirror
    |     column slips in without registration)
    |
    | Each entry shape:
    |   - column:     'users.<col>'  — the denormalised mirror
    |   - canonical:  '<table>.<col>' — the source of truth
    |   - key:        '<table>.<fk_to_users>' — how to join canonical to users
    |   - role_scope: optional list of roles this mirror applies to (defaults
    |                 to all). LandlordProfile only exists for landlords; the
    |                 mirror is scoped accordingly.
    |   - pinned:     true ⇒ any drift opens a sev3 alert (mirror_drift_count > 0).
    |                 false ⇒ sev4 at threshold 5/24h (translator-backlog grade).
    |
    */
    'mirrors' => [
        [
            'column' => 'users.profile_photo_path',
            'canonical' => 'landlord_profiles.profile_photo_path',
            'key' => 'landlord_profiles.user_id',
            'role_scope' => ['landlord', 'caretaker'],
            'pinned' => true,
        ],
        [
            'column' => 'users.emergency_contact_name',
            'canonical' => 'emergency_contacts.name',
            'key' => 'emergency_contacts.tenant_id',
            'canonical_filter' => ['is_primary' => true],
            'role_scope' => ['tenant'],
            'pinned' => true,
        ],
        [
            'column' => 'users.emergency_contact_phone',
            'canonical' => 'emergency_contacts.phone',
            'key' => 'emergency_contacts.tenant_id',
            'canonical_filter' => ['is_primary' => true],
            'role_scope' => ['tenant'],
            'pinned' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mirror Exempt (deprecation allow-list)
    |--------------------------------------------------------------------------
    |
    | Phase-46 CANONICAL-FIX-1: columns flagged as denormalisation candidates
    | by the registry sniff but exempted because they're slated for removal.
    | The CI watchdog accepts them without a registered listener; the
    | mirror-audit cron skips them. Re-list when the column actually drops.
    |
    */
    'mirror_exempt' => [
        [
            'column' => 'users.kyc_completed_at',
            'deprecated_at' => '2026-05-17',
            'remove_at' => '2026-08-17',
            'reason' => 'Write-only ghost column — User::hasCompletedKyc() reads dynamically from kyc_requirements + tenant_kyc_submissions; the column is set but never read.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mirror sniff regex
    |--------------------------------------------------------------------------
    |
    | Phase-46 CANONICAL-AUDIT-3: the watchdog walks users.* and matches
    | column names against this regex. Anything matched and not in
    | mirrors[] + mirror_exempt[] fails the test.
    |
    | Conservative pattern: catches *_name (emergency_contact_name),
    | *_phone, *_photo*, address_*, default_*, preferred_*. EXCLUDES
    | bare 'name', 'phone', 'email' on users — those are user identity,
    | not mirrors of a child table.
    |
    */
    'mirror_sniff_regex' => '/^(emergency|contact|address|default|preferred)_|_photo(_|$)|_phone$/',
];
