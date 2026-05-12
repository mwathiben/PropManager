<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Events\SuspiciousActivityDetected;
use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use App\Services\IncidentDetector;
use App\Services\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase-13 BREACH-2 regression coverage. Locks in:
 *   - each seed rule fires above its threshold and only above
 *   - debounce prevents one burst from creating N incidents
 *   - the SuspiciousActivityDetected event is dispatched per creation
 *   - the SecurityLogger seam (failed-login → checkFailedLoginBurst)
 *     escalates without the caller having to know
 */
class IncidentDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_burst_below_threshold_creates_nothing(): void
    {
        $detector = app(IncidentDetector::class);
        $email = 'target@example.test';

        // Seed 49 failed logins (default threshold = 50).
        $this->seedFailedLogins($email, 49);

        $incident = $detector->checkFailedLoginBurst($email);

        $this->assertNull($incident);
        $this->assertSame(0, SecurityIncident::count());
    }

    public function test_failed_login_burst_at_threshold_creates_high_severity_incident(): void
    {
        Event::fake();
        $detector = app(IncidentDetector::class);
        $email = 'target@example.test';

        $this->seedFailedLogins($email, 50);

        $incident = $detector->checkFailedLoginBurst($email);

        $this->assertNotNull($incident);
        $this->assertSame(SecurityIncident::SEVERITY_HIGH, $incident->severity);
        $this->assertSame(SecurityIncident::TYPE_DATA_BREACH, $incident->type);
        $this->assertContains('phase13_breach2:failed_login_burst', $incident->compliance_references);

        Event::assertDispatched(SuspiciousActivityDetected::class, function ($event) use ($incident) {
            return $event->incident->is($incident)
                && $event->rule === 'failed_login_burst';
        });
    }

    public function test_failed_login_burst_debounces_consecutive_calls(): void
    {
        $detector = app(IncidentDetector::class);
        $email = 'target@example.test';

        $this->seedFailedLogins($email, 60);

        $first = $detector->checkFailedLoginBurst($email);
        $second = $detector->checkFailedLoginBurst($email);

        $this->assertNotNull($first);
        $this->assertNull($second, 'debounce window must suppress a second incident');
        $this->assertSame(1, SecurityIncident::count());
    }

    public function test_large_data_export_only_fires_at_threshold(): void
    {
        $detector = app(IncidentDetector::class);

        $belowThreshold = $detector->checkLargeDataExport(userId: 7, rowCount: 9999, exportType: 'tenants');
        $atThreshold = $detector->checkLargeDataExport(userId: 7, rowCount: 10000, exportType: 'tenants');

        $this->assertNull($belowThreshold);
        $this->assertNotNull($atThreshold);
        $this->assertSame(SecurityIncident::SEVERITY_MEDIUM, $atThreshold->severity);
    }

    public function test_unauthorised_role_escalation_only_fires_for_landlord_promotion_without_invitation(): void
    {
        $detector = app(IncidentDetector::class);

        $tenantToTenant = $detector->checkUnauthorisedRoleEscalation(1, 'tenant', 'tenant', false);
        $landlordToLandlord = $detector->checkUnauthorisedRoleEscalation(1, 'landlord', 'landlord', false);
        $withInvitation = $detector->checkUnauthorisedRoleEscalation(1, 'tenant', 'landlord', true);
        $withoutInvitation = $detector->checkUnauthorisedRoleEscalation(1, 'tenant', 'landlord', false);

        $this->assertNull($tenantToTenant);
        $this->assertNull($landlordToLandlord);
        $this->assertNull($withInvitation);
        $this->assertNotNull($withoutInvitation);
        $this->assertSame(SecurityIncident::SEVERITY_HIGH, $withoutInvitation->severity);
    }

    public function test_webhook_signature_flood_fires_above_threshold_only(): void
    {
        $detector = app(IncidentDetector::class);
        $ip = '203.0.113.42';

        // Threshold default = 10 in 1m. Seed 11 entries.
        for ($i = 0; $i < 11; $i++) {
            SecurityLog::create([
                'ip_address' => $ip,
                'event_type' => 'webhook_signature_failed',
                'severity' => SecurityLog::SEVERITY_WARNING,
                'description' => 'signature mismatch',
                'metadata' => [],
                'is_suspicious' => true,
            ]);
        }

        $incident = $detector->checkWebhookSignatureFlood($ip);

        $this->assertNotNull($incident);
        $this->assertSame(SecurityIncident::SEVERITY_MEDIUM, $incident->severity);
    }

    public function test_security_logger_failed_login_seam_escalates_when_burst_threshold_crossed(): void
    {
        Event::fake();
        $logger = app(SecurityLogger::class);
        $email = 'target@example.test';

        for ($i = 0; $i < 50; $i++) {
            $logger->logFailedLogin($email);
        }

        $incident = SecurityIncident::query()
            ->whereJsonContains('compliance_references', 'phase13_breach2:failed_login_burst')
            ->first();

        $this->assertNotNull(
            $incident,
            'logFailedLogin must escalate via IncidentDetector once the burst threshold is crossed',
        );

        Event::assertDispatched(SuspiciousActivityDetected::class);
    }

    private function seedFailedLogins(string $email, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            SecurityLog::create([
                'user_id' => null,
                'landlord_id' => null,
                'event_type' => SecurityLog::EVENT_LOGIN_FAILED,
                'severity' => SecurityLog::SEVERITY_WARNING,
                'description' => "Failed login attempt for {$email}",
                'metadata' => ['email' => $email],
                'ip_address' => '198.51.100.'.($i % 250),
                'is_suspicious' => false,
            ]);
        }
    }
}
