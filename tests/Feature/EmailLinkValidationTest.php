<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Currency;
use App\Mail\CaretakerInvitation;
use App\Mail\CreditNoteIssued;
use App\Mail\DataExportReady;
use App\Mail\DepositRefundNotification;
use App\Mail\FailedWebhookAlert;
use App\Mail\InvoiceReminder;
use App\Mail\InvoiceSent;
use App\Mail\LandlordWelcome;
use App\Mail\NotificationMail;
use App\Mail\OverpaymentNotification;
use App\Mail\PaymentReceived;
use App\Mail\PaymentVerificationApproved;
use App\Mail\PaymentVerificationRejected;
use App\Mail\ReconciliationAlert;
use App\Mail\RentHikeNotice;
use App\Mail\TenantCredentials;
use App\Mail\TenantInvitationMail;
use App\Mail\TenantWelcome;
use App\Models\Building;
use App\Models\CreditNote;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ReconciliationReport;
use App\Models\TenantInvitation;
use App\Models\TenantPaymentVerification;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookDeadLetter;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;

class EmailLinkValidationTest extends TestCase
{
    use RefreshDatabase;

    private const UNSAFE_SCHEMES = ['javascript:', 'data:', 'vbscript:'];

    private const SIGNED_URL_MAILABLES = [
        PaymentReceived::class,
        InvoiceSent::class,
        InvoiceReminder::class,
        TenantWelcome::class,
        TenantCredentials::class,
        PaymentVerificationApproved::class,
        PaymentVerificationRejected::class,
        RentHikeNotice::class,
        NotificationMail::class,
    ];

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->building = Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::KES)
            ->create();
    }

    public function test_all_mailables_have_no_dangerous_url_schemes(): void
    {
        $mailables = $this->buildAllMailables();

        foreach ($mailables as $name => $mailable) {
            $html = $mailable->render();
            $hrefs = $this->extractHrefs($html);
            $this->assertNoUnsafeSchemes($hrefs, $name);
        }
    }

    public function test_all_mailables_internal_routes_resolve(): void
    {
        $mailables = $this->buildAllMailables();
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $resolvedCount = 0;

        foreach ($mailables as $name => $mailable) {
            $html = $mailable->render();
            $hrefs = $this->extractHrefs($html);
            $resolvedCount += $this->assertInternalRoutesResolve($hrefs, $appHost, $name);
        }

        $this->assertGreaterThan(0, $resolvedCount, 'At least one internal route should be validated across all mailables');
    }

    public function test_no_localhost_urls_in_production_config(): void
    {
        $productionUrl = 'https://app.propmanager.com';
        config(['app.url' => $productionUrl]);
        URL::forceRootUrl($productionUrl);

        $mailables = $this->buildAllMailables();

        foreach ($mailables as $name => $mailable) {
            $html = $mailable->render();
            $hrefs = $this->extractHrefs($html);
            $this->assertLocalhostFree($hrefs, $name);
        }
    }

    public function test_signed_url_mailables_contain_signature_parameter(): void
    {
        $mailables = $this->buildAllMailables();

        foreach (self::SIGNED_URL_MAILABLES as $class) {
            $shortName = class_basename($class);
            $this->assertArrayHasKey($shortName, $mailables, "Missing mailable: {$shortName}");

            $html = $mailables[$shortName]->render();
            $hrefs = $this->extractHrefs($html);

            $this->assertTrue(
                $this->anyHrefContainsSignature($hrefs),
                "{$shortName} should contain at least one signed URL with signature= parameter"
            );
        }
    }

    /**
     * @return array<string, Mailable>
     */
    private function buildAllMailables(): array
    {
        return array_merge(
            $this->buildInvoiceMailables(),
            $this->buildTenantMailables(),
            $this->buildVerificationMailables(),
            $this->buildSystemMailables()
        );
    }

    /**
     * @return array<string, Mailable>
     */
    private function buildInvoiceMailables(): array
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $invoice = Invoice::factory()->forLease($lease)->sent()->create();
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 5000,
            'currency' => Currency::KES,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'LINK-VAL-'.uniqid(),
        ]);

        return [
            'PaymentReceived' => new PaymentReceived($payment, $invoice),
            'InvoiceSent' => new InvoiceSent($invoice),
            'InvoiceReminder' => new InvoiceReminder($invoice),
        ];
    }

    /**
     * @return array<string, Mailable>
     */
    private function buildTenantMailables(): array
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);
        $invitation = Invitation::factory()->forLandlord($this->landlord)->create();
        $tenantInvitation = TenantInvitation::factory()
            ->forUnit($unit)
            ->create(['landlord_id' => $this->landlord->id]);

        $depositLease = Lease::factory()->forUnit(
            Unit::factory()->forBuilding($this->building)->create()
        )->active()->create();
        $depositLease->update([
            'deposit_refund_amount' => 20000,
            'deposit_deductions' => 5000,
            'deposit_deduction_reason' => 'Cleaning fee',
        ]);

        return [
            'CaretakerInvitation' => new CaretakerInvitation($invitation),
            'TenantInvitationMail' => new TenantInvitationMail($tenantInvitation),
            'TenantWelcome' => new TenantWelcome($tenant, $tenantInvitation, $lease),
            'TenantCredentials' => new TenantCredentials($tenant, $lease, 'TempPass123!', $this->landlord),
            'LandlordWelcome' => new LandlordWelcome($this->landlord),
            'RentHikeNotice' => new RentHikeNotice($lease, 25000, 30000, 'March 1, 2026'),
            'DepositRefundNotification' => new DepositRefundNotification($depositLease->fresh(), 'partial_refund'),
        ];
    }

    /**
     * @return array<string, Mailable>
     */
    private function buildVerificationMailables(): array
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();

        $approved = TenantPaymentVerification::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'status' => TenantPaymentVerification::STATUS_PAYMENT_VERIFIED,
            'deposit_required' => 25000,
            'first_rent_required' => 25000,
            'other_charges' => 0,
            'total_required' => 50000,
            'amount_paid' => 50000,
            'verified_at' => now(),
        ]);

        $rejected = TenantPaymentVerification::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'status' => TenantPaymentVerification::STATUS_REJECTED,
            'deposit_required' => 25000,
            'first_rent_required' => 25000,
            'other_charges' => 0,
            'total_required' => 50000,
            'rejection_reason' => 'Invalid payment proof',
        ]);

        return [
            'PaymentVerificationApproved' => new PaymentVerificationApproved($approved),
            'PaymentVerificationRejected' => new PaymentVerificationRejected($rejected),
        ];
    }

    /**
     * @return array<string, Mailable>
     */
    private function buildSystemMailables(): array
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);
        $invoice = Invoice::factory()->forLease($lease)->sent()->create();

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 30000,
            'currency' => Currency::KES,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'SYS-LINK-'.uniqid(),
        ]);

        $creditNote = CreditNote::create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $lease->id,
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'credit_number' => 'CN-'.uniqid(),
            'amount' => 5000,
            'applied_amount' => 0,
            'reason' => CreditNote::REASON_OVERPAYMENT,
            'status' => CreditNote::STATUS_APPROVED,
        ]);

        $deadLetter = WebhookDeadLetter::factory()
            ->forLandlord($this->landlord)
            ->mpesa()
            ->create();

        $report = ReconciliationReport::factory()
            ->withDiscrepancies(2)
            ->create(['landlord_id' => $this->landlord->id]);

        return [
            'OverpaymentNotification' => new OverpaymentNotification($payment, $lease, $tenant, 500, 500),
            'CreditNoteIssued' => new CreditNoteIssued($creditNote),
            'DataExportReady' => new DataExportReady($this->landlord, 'exports/test-export.zip'),
            'FailedWebhookAlert' => new FailedWebhookAlert($deadLetter),
            'ReconciliationAlert' => new ReconciliationAlert($report),
            'NotificationMail' => new NotificationMail(
                'Test Notification',
                'This is a test notification body.',
                ['key' => 'value'],
                $tenant
            ),
        ];
    }

    /**
     * @return string[]
     */
    private function extractHrefs(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $links = $dom->getElementsByTagName('a');
        $hrefs = [];

        foreach ($links as $link) {
            if ($link->hasAttribute('href')) {
                $hrefs[] = $link->getAttribute('href');
            }
        }

        return $hrefs;
    }

    /**
     * @param  string[]  $hrefs
     */
    private function assertNoUnsafeSchemes(array $hrefs, string $mailableName): void
    {
        foreach ($hrefs as $href) {
            $lower = strtolower(trim($href));
            foreach (self::UNSAFE_SCHEMES as $scheme) {
                $this->assertStringStartsNotWith(
                    $scheme,
                    $lower,
                    "{$mailableName}: found dangerous URL scheme '{$scheme}' in href: {$href}"
                );
            }
        }
    }

    /**
     * @param  string[]  $hrefs
     */
    private function assertInternalRoutesResolve(array $hrefs, string $appHost, string $mailableName): int
    {
        $count = 0;

        foreach ($hrefs as $href) {
            $parsed = parse_url($href);
            if (! isset($parsed['host']) || $parsed['host'] !== $appHost) {
                continue;
            }

            $path = $parsed['path'] ?? '/';
            $this->resolveRoute($path, $mailableName, $href);
            $count++;
        }

        return $count;
    }

    private function resolveRoute(string $path, string $mailableName, string $href): void
    {
        try {
            $request = Request::create($path);
            Route::getRoutes()->match($request);
        } catch (RouteNotFoundException) {
            $this->fail("{$mailableName}: internal route not found for path '{$path}' (href: {$href})");
        } catch (MethodNotAllowedHttpException) {
            // POST-only routes (e.g., email.unsubscribe) won't match GET — acceptable
        }
    }

    /**
     * @param  string[]  $hrefs
     */
    private function assertLocalhostFree(array $hrefs, string $mailableName): void
    {
        $localhostPatterns = ['localhost', '127.0.0.1', '0.0.0.0'];

        foreach ($hrefs as $href) {
            $parsed = parse_url($href);
            $host = $parsed['host'] ?? '';

            foreach ($localhostPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $host,
                    "{$mailableName}: found localhost URL in production config: {$href}"
                );
            }
        }
    }

    /**
     * @param  string[]  $hrefs
     */
    private function anyHrefContainsSignature(array $hrefs): bool
    {
        foreach ($hrefs as $href) {
            if (str_contains($href, 'signature=')) {
                return true;
            }
        }

        return false;
    }
}
