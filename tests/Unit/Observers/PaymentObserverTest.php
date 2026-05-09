<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Jobs\WarmFinanceCacheJob;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use App\Services\FinanceCacheService;
use App\Services\PaymentLinkService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentObserverTest extends TestCase
{
    protected MockInterface $paymentLinkService;

    protected PaymentObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentLinkService = Mockery::mock(PaymentLinkService::class);
        $this->observer = new PaymentObserver($this->paymentLinkService);
    }

    #[Test]
    public function created_invalidates_finance_cache_for_landlord(): void
    {
        Cache::spy();

        $payment = new Payment(['landlord_id' => 42, 'invoice_id' => 10]);

        $this->paymentLinkService->shouldReceive('revokeForInvoice')->once();

        $this->observer->created($payment);

        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('hub', 42));
        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('overview', 42, now()->format('Y-m')));
        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('trend', 42));
        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('arrears', 42));
        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('deposits', 42));
        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('latefees', 42));
        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('expenses', 42));
    }

    #[Test]
    public function created_revokes_payment_links_for_invoice(): void
    {
        Cache::spy();

        $payment = new Payment(['landlord_id' => 1, 'invoice_id' => 99]);

        $this->paymentLinkService
            ->shouldReceive('revokeForInvoice')
            ->once()
            ->with(99, 1)
            ->andReturn(2);

        $this->observer->created($payment);
    }

    #[Test]
    public function created_skips_link_revocation_when_no_invoice(): void
    {
        Cache::spy();

        $payment = new Payment(['landlord_id' => 1, 'invoice_id' => null]);

        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->created($payment);
    }

    #[Test]
    public function created_skips_cache_when_no_landlord_id(): void
    {
        Cache::spy();

        $payment = new Payment(['landlord_id' => null, 'invoice_id' => null]);

        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->created($payment);

        Cache::shouldNotHaveReceived('forget');
    }

    #[Test]
    public function updated_invalidates_cache_but_not_links(): void
    {
        Cache::spy();

        $payment = new Payment(['landlord_id' => 7, 'invoice_id' => 55]);

        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->updated($payment);

        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('hub', 7));
    }

    #[Test]
    public function deleted_invalidates_cache_but_not_links(): void
    {
        Cache::spy();

        $payment = new Payment(['landlord_id' => 7, 'invoice_id' => 55]);

        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->deleted($payment);

        Cache::shouldHaveReceived('forget')
            ->with(FinanceCacheService::statsKey('hub', 7));
    }

    #[Test]
    public function cache_invalidation_clears_all_seven_stat_keys(): void
    {
        Cache::spy();

        $payment = new Payment(['landlord_id' => 5, 'invoice_id' => null]);
        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->created($payment);

        $expectedKeys = [
            FinanceCacheService::statsKey('hub', 5),
            FinanceCacheService::statsKey('overview', 5, now()->format('Y-m')),
            FinanceCacheService::statsKey('trend', 5),
            FinanceCacheService::statsKey('arrears', 5),
            FinanceCacheService::statsKey('deposits', 5),
            FinanceCacheService::statsKey('latefees', 5),
            FinanceCacheService::statsKey('expenses', 5),
        ];

        foreach ($expectedKeys as $key) {
            Cache::shouldHaveReceived('forget')->with($key);
        }
    }

    #[Test]
    public function created_dispatches_cache_warming_job(): void
    {
        Queue::fake();
        Cache::spy();

        $payment = new Payment(['landlord_id' => 42, 'invoice_id' => null]);
        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->created($payment);

        Queue::assertPushed(WarmFinanceCacheJob::class, function ($job) {
            return $job->landlordId === 42;
        });
    }

    #[Test]
    public function created_does_not_dispatch_warming_without_landlord(): void
    {
        Queue::fake();
        Cache::spy();

        $payment = new Payment(['landlord_id' => null, 'invoice_id' => null]);
        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->created($payment);

        Queue::assertNotPushed(WarmFinanceCacheJob::class);
    }

    #[Test]
    public function updated_does_not_dispatch_warming_job(): void
    {
        Queue::fake();
        Cache::spy();

        $payment = new Payment(['landlord_id' => 7, 'invoice_id' => 55]);
        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->updated($payment);

        Queue::assertNotPushed(WarmFinanceCacheJob::class);
    }

    #[Test]
    public function deleted_does_not_dispatch_warming_job(): void
    {
        Queue::fake();
        Cache::spy();

        $payment = new Payment(['landlord_id' => 7, 'invoice_id' => 55]);
        $this->paymentLinkService->shouldNotReceive('revokeForInvoice');

        $this->observer->deleted($payment);

        Queue::assertNotPushed(WarmFinanceCacheJob::class);
    }
}
