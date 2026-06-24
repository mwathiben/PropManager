<?php

namespace App\Services;

use App\Mail\BreachAffectedSubjectNotice;
use App\Mail\BreachReportedAlert;
use App\Models\AuditLog;
use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Kenya Data Protection Act 2019 Compliance Service
 *
 * This service implements requirements specific to the Kenya DPA including:
 * - Data Controller obligations (Section 25)
 * - Rights of Data Subjects (Sections 26-31)
 * - Cross-border data transfer restrictions (Section 48)
 * - Sensitive personal data handling (Section 44)
 * - Breach notification requirements (Section 43)
 */
class KenyaDpaService
{
    /**
     * Sensitive personal data categories under Kenya DPA.
     */
    public const SENSITIVE_DATA_CATEGORIES = [
        'national_id',
        'ethnic_origin',
        'health_data',
        'biometric_data',
        'genetic_data',
        'sex_life',
        'sexual_orientation',
        'political_opinion',
        'religious_belief',
        'trade_union_membership',
        'criminal_record',
    ];

    /**
     * Lawful bases for processing under Kenya DPA Section 30.
     */
    public const LAWFUL_BASES = [
        'consent' => 'Data subject has given consent',
        'contract' => 'Processing is necessary for contract performance',
        'legal_obligation' => 'Processing is necessary for legal compliance',
        'vital_interests' => 'Processing is necessary to protect vital interests',
        'public_interest' => 'Processing is necessary for public interest',
        'legitimate_interests' => 'Processing is necessary for legitimate interests',
    ];

    /**
     * Check if data contains sensitive personal data.
     */
    public function containsSensitiveData(array $data): array
    {
        $found = [];

        foreach (self::SENSITIVE_DATA_CATEGORIES as $category) {
            if (array_key_exists($category, $data) && ! empty($data[$category])) {
                $found[] = $category;
            }
        }

        return $found;
    }

    /**
     * Get the lawful basis for processing a specific data type.
     */
    public function getLawfulBasis(string $dataType, string $processingPurpose): string
    {
        // For property management, most processing is contract-based
        $contractBased = [
            'tenant_info' => 'contract',
            'lease_data' => 'contract',
            'payment_data' => 'contract',
            'contact_info' => 'contract',
        ];

        // Some processing requires consent
        $consentBased = [
            'marketing' => 'consent',
            'third_party_sharing' => 'consent',
            'profile_analytics' => 'consent',
        ];

        // Legal obligations
        $legalBased = [
            'national_id' => 'legal_obligation', // KYC requirements
            'tax_records' => 'legal_obligation',
        ];

        if (isset($contractBased[$dataType])) {
            return $contractBased[$dataType];
        }

        if (isset($consentBased[$dataType])) {
            return $consentBased[$dataType];
        }

        if (isset($legalBased[$dataType])) {
            return $legalBased[$dataType];
        }

        return 'legitimate_interests';
    }

    /**
     * Log data processing activity for compliance records.
     */
    public function logDataProcessing(
        User $dataSubject,
        string $dataType,
        string $processingActivity,
        string $lawfulBasis,
        ?array $metadata = null
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'landlord_id' => $dataSubject->isScopeOwner() ? $dataSubject->id : $dataSubject->landlord_id,
            'event_type' => 'data_processing',
            'auditable_type' => User::class,
            'auditable_id' => $dataSubject->id,
            'metadata' => array_merge([
                'data_type' => $dataType,
                'processing_activity' => $processingActivity,
                'lawful_basis' => $lawfulBasis,
                'lawful_basis_description' => self::LAWFUL_BASES[$lawfulBasis] ?? $lawfulBasis,
                'compliance' => 'kenya_dpa_section_30',
            ], $metadata ?? []),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Check if cross-border data transfer is allowed.
     *
     * Kenya DPA Section 48 requires adequate protection for cross-border transfers.
     */
    public function canTransferCrossBorder(string $destinationCountry): array
    {
        // Countries with adequate protection (this list should be updated based on ODPC guidance)
        $adequateProtection = [
            'KE', // Kenya itself
            'EU', // European Union countries (GDPR adequacy)
            'GB', // United Kingdom
            'CA', // Canada
            'NZ', // New Zealand
            'JP', // Japan
            'KR', // South Korea
            'AR', // Argentina
            'UY', // Uruguay
            'IL', // Israel
            'CH', // Switzerland
        ];

        $allowed = in_array($destinationCountry, $adequateProtection);

        return [
            'allowed' => $allowed,
            'requires_safeguards' => ! $allowed,
            'safeguards_required' => ! $allowed ? [
                'Standard Contractual Clauses',
                'Binding Corporate Rules',
                'Explicit Consent',
                'ODPC Authorization',
            ] : [],
            'compliance_reference' => 'Kenya DPA Section 48',
        ];
    }

    /**
     * Initiate a data breach notification (72-hour requirement).
     *
     * Kenya DPA Section 43 requires notification within 72 hours.
     */
    public function initiateBreachNotification(
        string $breachDescription,
        array $affectedDataTypes,
        int $estimatedAffectedUsers,
        string $mitigationMeasures,
        ?int $reportedBy = null
    ): SecurityIncident {
        $incident = SecurityIncident::create([
            'type' => 'data_breach',
            'severity' => $this->assessBreachSeverity($affectedDataTypes, $estimatedAffectedUsers),
            'description' => $breachDescription,
            'affected_data_types' => $affectedDataTypes,
            'estimated_affected_users' => $estimatedAffectedUsers,
            'mitigation_measures' => $mitigationMeasures,
            'reported_by' => $reportedBy ?? auth()->id(),
            'reported_at' => now(),
            'notification_deadline' => now()->addHours(72),
            // Phase-13 BREACH-7: 30-day post-incident review deadline.
            'review_due_at' => now()->addDays(30),
            'status' => 'reported',
            'compliance_references' => [
                'kenya_dpa_section_43',
                'gdpr_article_33',
            ],
        ]);

        // Log the incident
        Log::channel('security')->critical('Data breach reported', [
            'incident_id' => $incident->id,
            'affected_users' => $estimatedAffectedUsers,
            'data_types' => $affectedDataTypes,
        ]);

        // Send immediate notification to administrators
        $this->notifyAdministrators($incident);

        return $incident;
    }

    /**
     * Phase-13 DPA-10: Section 33 / Article 8 — children's data
     * special handling. Returns true when the supplied date of birth
     * resolves to under 18. PropManager's policy is to NOT knowingly
     * accept tenants under 18; the helper is the gate any future
     * KYC-submission code should call before persisting a tenant.
     * Out-of-band parental consent artefacts are an operator
     * concern (see docs/legal/childrens-data-policy.md).
     */
    public function isMinor(string $dateOfBirth, int $minimumAge = 18): bool
    {
        try {
            $dob = new \DateTimeImmutable($dateOfBirth);
        } catch (\Exception) {
            // Malformed date — treat as 'unknown', fail-safe = true
            // so the gate trips and operator must investigate.
            return true;
        }

        $today = new \DateTimeImmutable('today');
        $age = $today->diff($dob)->y;

        return $age < $minimumAge;
    }

    /**
     * Phase-21 DEFER-DPA-1: gate predicate for processing minor data
     * without parental consent. Returns true when a tenant has a dob
     * indicating minor status BUT no parental_consent_provided_at
     * timestamp — TenantPolicy::update + downstream operations call
     * this to deny processing until the consent artefact is on file.
     *
     * Returns false when:
     *   - dob is NULL (not enough info to determine — operator process)
     *   - dob indicates adult
     *   - dob indicates minor AND parental_consent_provided_at is set
     */
    public function minorRequiresConsent(User $tenant): bool
    {
        if ($tenant->dob === null) {
            return false;
        }

        $dobString = $tenant->dob instanceof \DateTimeInterface
            ? $tenant->dob->format('Y-m-d')
            : (string) $tenant->dob;

        if (! $this->isMinor($dobString)) {
            return false;
        }

        return $tenant->parental_consent_provided_at === null;
    }

    /**
     * Phase-13 BREACH-4: notify affected data subjects per Kenya DPA
     * Section 43(2) / GDPR Article 34. Called by the operator (via
     * controller or dpa:notify-affected-subjects) when the breach is
     * likely to result in high risk to subject rights — not every
     * breach triggers this. Returns the count of mailables queued.
     *
     * After dispatch, marks users_notified_at on the incident and
     * writes a SecurityLog row preserving the user-id list for audit.
     *
     * @param  array<int>  $userIds  data subject user ids to notify
     */
    public function notifyAffectedSubjects(SecurityIncident $incident, array $userIds): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return 0;
        }

        $users = User::whereIn('id', $userIds)->get();
        $queued = 0;

        foreach ($users as $user) {
            if (! $user->email) {
                continue;
            }
            Mail::to($user->email)->queue(new BreachAffectedSubjectNotice($incident, $user));
            $queued++;
        }

        if ($queued === 0) {
            return 0;
        }

        $incident->markUsersNotified();

        SecurityLog::create([
            'user_id' => auth()->id(),
            'landlord_id' => null,
            'event_type' => 'breach_subjects_notified',
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => "Affected-subject notification dispatched for incident #{$incident->id}",
            'metadata' => [
                'incident_id' => $incident->id,
                'subject_count' => $queued,
                'user_ids' => $users->pluck('id')->all(),
                'compliance' => 'kenya_dpa_section_43_2',
            ],
            'is_suspicious' => false,
        ]);

        Log::channel(config('security.logging.channel', 'security'))->warning(
            'Article 34 / Section 43(2) affected-subject notification dispatched',
            ['incident_id' => $incident->id, 'queued' => $queued]
        );

        return $queued;
    }

    /**
     * Phase-13 BREACH-8: severity auto-classification helper. Public
     * counterpart of assessBreachSeverity so callers (or the ops
     * surface) can pre-compute severity from a known dataset before
     * the breach is recorded. The classifier:
     *   - sensitive_data + financial_data + >100 affected → critical
     *   - sensitive_data OR >500 affected               → high
     *   - financial_data OR >50 affected                → medium
     *   - otherwise                                     → low
     */
    public function classifySeverity(int $affectedCount, bool $sensitiveData, bool $financialData): string
    {
        if ($sensitiveData && $affectedCount > 100) {
            return SecurityIncident::SEVERITY_CRITICAL;
        }
        if ($sensitiveData || $affectedCount > 500) {
            return SecurityIncident::SEVERITY_HIGH;
        }
        if ($financialData || $affectedCount > 50) {
            return SecurityIncident::SEVERITY_MEDIUM;
        }

        return SecurityIncident::SEVERITY_LOW;
    }

    /**
     * Internal severity assessment used by initiateBreachNotification —
     * delegates to classifySeverity. financial_data is derived from
     * the data types: if 'bank_details' or 'payment_data' is in the
     * affected list, financial_data = true.
     */
    protected function assessBreachSeverity(array $affectedDataTypes, int $affectedUsers): string
    {
        $hasSensitiveData = ! empty(array_intersect($affectedDataTypes, self::SENSITIVE_DATA_CATEGORIES));
        $hasFinancialData = ! empty(array_intersect($affectedDataTypes, [
            'bank_details',
            'payment_data',
            'card_number',
            'account_number',
        ]));

        return $this->classifySeverity($affectedUsers, $hasSensitiveData, $hasFinancialData);
    }

    /**
     * Phase-13 BREACH-1: page the configured ops channel + every
     * super-admin user via BreachReportedAlert. Previously this method
     * wrote a Log::info line per admin and nothing else — a breach
     * could be recorded with no human paged. The dedicated ops email
     * (KENYA_DPA_BREACH_EMAIL) is required for Section 43 / Article 33
     * timeliness; the per-admin fan-out remains for redundancy.
     */
    protected function notifyAdministrators(SecurityIncident $incident): void
    {
        $opsRecipient = config('security.kenya_dpa.breach_notification_email');
        if ($opsRecipient) {
            Mail::to($opsRecipient)->queue(new BreachReportedAlert($incident));
        } else {
            Log::channel(config('security.logging.channel', 'security'))->warning(
                'KENYA_DPA_BREACH_EMAIL is not configured — breach notification email skipped',
                ['incident_id' => $incident->id]
            );
        }

        $admins = User::where('role', 'super_admin')->get();
        foreach ($admins as $admin) {
            if (! $admin->email) {
                continue;
            }
            Mail::to($admin->email)->queue(new BreachReportedAlert($incident));
        }
    }

    /**
     * Generate a Data Protection Impact Assessment (DPIA) template.
     *
     * Required under Kenya DPA for high-risk processing activities.
     */
    public function generateDpiaTemplate(string $processingActivity): array
    {
        return [
            'title' => "Data Protection Impact Assessment - {$processingActivity}",
            'date' => now()->format('Y-m-d'),
            'prepared_by' => auth()->user()?->name ?? 'System',
            'sections' => [
                [
                    'title' => '1. Description of Processing',
                    'questions' => [
                        'What personal data will be processed?',
                        'What is the purpose of processing?',
                        'Who are the data subjects?',
                        'How long will data be retained?',
                        'Who will have access to the data?',
                    ],
                ],
                [
                    'title' => '2. Lawful Basis Assessment',
                    'questions' => [
                        'What is the lawful basis for processing? (Section 30)',
                        'If consent, how will it be obtained?',
                        'If legitimate interests, what is the balancing test result?',
                    ],
                ],
                [
                    'title' => '3. Necessity and Proportionality',
                    'questions' => [
                        'Is the processing necessary for the stated purpose?',
                        'Are there less intrusive alternatives?',
                        'Is the data collected the minimum necessary?',
                    ],
                ],
                [
                    'title' => '4. Risk Assessment',
                    'questions' => [
                        'What risks does processing pose to data subjects?',
                        'What is the likelihood of each risk?',
                        'What is the severity of potential harm?',
                    ],
                    'risk_matrix' => [
                        'categories' => ['Unauthorized access', 'Data loss', 'Misuse', 'Discrimination'],
                        'levels' => ['Low', 'Medium', 'High', 'Critical'],
                    ],
                ],
                [
                    'title' => '5. Risk Mitigation Measures',
                    'questions' => [
                        'What technical measures are in place?',
                        'What organizational measures are in place?',
                        'Are there any residual risks?',
                    ],
                ],
                [
                    'title' => '6. Consultation',
                    'questions' => [
                        'Have data subjects been consulted?',
                        'Has the ODPC been consulted (if required)?',
                        'Have relevant stakeholders reviewed this DPIA?',
                    ],
                ],
                [
                    'title' => '7. Sign-Off',
                    'fields' => [
                        'Data Controller Representative',
                        'DPO (if applicable)',
                        'Date of Approval',
                        'Review Date',
                    ],
                ],
            ],
            'compliance_references' => [
                'Kenya DPA Section 31 - Data Protection Impact Assessment',
                'GDPR Article 35 - Data Protection Impact Assessment',
            ],
        ];
    }

    /**
     * Generate compliance status report.
     */
    public function generateComplianceReport(?int $landlordId = null): array
    {
        $query = AuditLog::query();

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        $thirtyDaysAgo = now()->subDays(30);

        return [
            'report_date' => now()->format('Y-m-d H:i:s'),
            'reporting_period' => [
                'from' => $thirtyDaysAgo->format('Y-m-d'),
                'to' => now()->format('Y-m-d'),
            ],
            'statistics' => [
                'data_exports_requested' => $query->clone()
                    ->where('event_type', 'data_exported')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'deletion_requests' => $query->clone()
                    ->where('event_type', 'deletion_requested')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'consent_records' => $query->clone()
                    ->whereIn('event_type', ['consent_granted', 'consent_withdrawn'])
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'security_incidents' => SecurityIncident::where('created_at', '>=', $thirtyDaysAgo)->count(),
            ],
            'compliance_checklist' => [
                'data_controller_registration' => 'Verify registration with ODPC',
                'privacy_policy_current' => 'Verify privacy policy is up to date',
                'consent_records_maintained' => 'Consent records are being maintained',
                'data_subject_requests_handled' => 'All DSR requests handled within timeframes',
                'breach_notification_process' => 'Breach notification process is documented',
                'dpia_conducted' => 'DPIA conducted for high-risk processing',
                'cross_border_safeguards' => 'Cross-border transfer safeguards in place',
            ],
            'regulatory_contacts' => [
                'authority' => 'Office of the Data Protection Commissioner (ODPC)',
                'address' => 'Uchumi House, 5th Floor, Aga Khan Walk, Nairobi',
                'email' => 'info@odpc.go.ke',
                'website' => 'https://www.odpc.go.ke',
            ],
        ];
    }

    /**
     * Phase-12 RETAIN-5 (PARTIAL): the values returned below are
     * authoritative for documentation/compliance display, but are
     * NOT enforced automatically. A future `dpa:enforce-retention`
     * command will walk this array and drive per-category purges.
     *
     * Today the per-category sweeps are handled separately:
     *   - audit_logs / security_logs  → logs:prune
     *   - soft-deleted models         → soft-deleted:purge
     *   - account-deletion grace      → gdpr:process-deletions
     *   - resolved webhook DLQ        → logs:prune --table=dead-letter
     *   - financial archive (7y)      → ArchiveOldPayments
     *   - export files (7d)           → exports:cleanup
     *
     * Still missing automated enforcement: notifications (1y),
     * consent records (consent + 3y), KYC photos past the lease+7y
     * window. Those are RETAIN-5 follow-up scope.
     *
     * Get data retention requirements.
     */
    public function getRetentionRequirements(): array
    {
        return [
            'tenant_data' => [
                'retention_period' => '7 years after lease termination',
                'basis' => 'Tax and legal requirements',
                'compliance' => 'Kenya DPA Section 39',
            ],
            'payment_records' => [
                'retention_period' => '7 years',
                'basis' => 'Financial regulations and tax law',
                'compliance' => 'Kenya DPA Section 39',
            ],
            'consent_records' => [
                'retention_period' => 'Duration of consent + 3 years',
                'basis' => 'Proof of consent',
                'compliance' => 'Kenya DPA Section 32',
            ],
            'audit_logs' => [
                'retention_period' => '1 year (standard) / 7 years (financial)',
                'basis' => 'Security and compliance',
                'compliance' => 'Kenya DPA Section 41',
            ],
            'security_incidents' => [
                'retention_period' => '10 years',
                'basis' => 'Legal proceedings and compliance',
                'compliance' => 'Kenya DPA Section 43',
            ],
            'marketing_data' => [
                'retention_period' => 'Until consent withdrawn',
                'basis' => 'Consent-based processing',
                'compliance' => 'Kenya DPA Section 32',
            ],
        ];
    }
}
