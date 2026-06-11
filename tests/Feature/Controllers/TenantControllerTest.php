<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TenantControllerTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    /**
     * Regression: the global ConvertEmptyStringsToNull middleware turns an
     * empty `?search=` query param into null, so $request->get('search', '')
     * returns null (the default only applies to an ABSENT key). That null
     * then reached TenantIndexService's `string $search` parameters and 500'd
     * the page — reproduced for every tab.
     */
    #[DataProvider('tabsProvider')]
    public function test_index_with_empty_search_does_not_500(string $tab): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $response = $this->actingAs($landlord)
            ->get("/tenants?search=&tab={$tab}");

        $response->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function tabsProvider(): array
    {
        return [
            'active tab' => ['active'],
            'past tab' => ['past'],
            'pending tab' => ['pending'],
        ];
    }
}
