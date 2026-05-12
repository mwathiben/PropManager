<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-15 Phase 2 coverage:
 *   PERF-1: payments composite indexes exist
 *   PERF-2: invoices composite index exists
 *   PERF-3: per_page cap on the 5 affected API controllers
 *   PERF-4: query count on a 15-row InvoiceController index is O(1)
 */
#[Group('api')]
class PerformanceHardeningTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_payments_table_has_landlord_date_composite_index(): void
    {
        // PERF-1 — already shipped by 2026_01_10 add_unique_constraint
        // _to_paystack_reference. Locking it in so a future migration
        // dropping the index gets caught.
        $indexes = $this->indexesOn('payments');
        $this->assertContains('payments_landlord_date_idx', $indexes);
    }

    public function test_invoices_table_has_landlord_status_due_composite_index(): void
    {
        // PERF-2 — already shipped by 2026_01_15 add_finance_hub_indexes.
        // Locking it in.
        $indexes = $this->indexesOn('invoices');
        $this->assertContains('invoices_landlord_status_due_idx', $indexes);
    }

    public function test_notifications_table_has_recipient_read_composite_index(): void
    {
        // PERF-7 (moved up from Phase 3). Badge query uses this.
        $indexes = $this->indexesOn('notifications');
        $this->assertContains('notifications_recipient_read_idx', $indexes);
    }

    public function test_per_page_clamps_to_max_on_invoice_index(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $tenantSetup = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        Invoice::factory()->count(5)->create([
            'landlord_id' => $landlord->id,
            'lease_id' => $tenantSetup['lease']->id,
        ]);

        Sanctum::actingAs($landlord, ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/invoices?per_page=99999');
        $response->assertOk();

        $perPage = (int) ($response->json('meta.per_page') ?? 0);
        $this->assertLessThanOrEqual(
            200,
            $perPage,
            'per_page must be clamped to 200',
        );
    }

    public function test_per_page_rejects_non_numeric(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        Sanctum::actingAs($setup['landlord'], ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/invoices?per_page=foo');
        $response->assertStatus(422);
    }

    public function test_per_page_accepts_default_when_omitted(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        Sanctum::actingAs($setup['landlord'], ['landlord:manage']);

        $response = $this->getJson('/api/v1/landlord/invoices');
        $response->assertOk();
        $this->assertSame(20, $response->json('meta.per_page'));
    }

    public function test_invoice_index_query_count_is_constant_under_n_invoices(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $tenantSetup = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        Invoice::factory()->count(15)->create([
            'landlord_id' => $landlord->id,
            'lease_id' => $tenantSetup['lease']->id,
        ]);

        Sanctum::actingAs($landlord, ['landlord:manage']);

        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/landlord/invoices?per_page=15');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        // Constant-query budget: auth + count + page-of-invoices +
        // eager-loaded lease + unit + building + tenant. ~10 queries
        // total for the full index page; spec is < 20.
        $this->assertLessThan(
            20,
            $count,
            "Invoice index page should be O(1), saw {$count} queries",
        );
    }

    /**
     * @return list<string>
     */
    private function indexesOn(string $table): array
    {
        $connection = Schema::getConnection();
        $schemaBuilder = $connection->getSchemaBuilder();

        // getIndexes returns array of associative arrays — each row's
        // 'name' is the index name.
        return collect($schemaBuilder->getIndexes($table))
            ->pluck('name')
            ->all();
    }
}
