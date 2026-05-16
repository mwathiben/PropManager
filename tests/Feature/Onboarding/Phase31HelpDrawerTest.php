<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\HelpArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase31HelpDrawerTest extends TestCase
{
    use RefreshDatabase;

    public function test_contextual_endpoint_returns_articles_matching_help_key(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        HelpArticle::create([
            'title' => 'Recording your first payment',
            'slug' => 'first-payment',
            'content' => 'Click the Record Payment button on any invoice.',
            'category' => 'finances',
            'help_key' => 'finances.payments.index',
            'is_published' => true,
        ]);
        HelpArticle::create([
            'title' => 'Generating an invoice',
            'slug' => 'generate-invoice',
            'content' => 'Open the invoice generator from the invoice list.',
            'category' => 'finances',
            'help_key' => 'finances.invoices.index',
            'is_published' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/help/contextual?key=finances.payments.index')
            ->assertOk()
            ->json('articles');

        $this->assertCount(1, $response);
        $this->assertSame('first-payment', $response[0]['slug']);
        $this->assertArrayHasKey('excerpt', $response[0]);
    }

    public function test_contextual_endpoint_returns_empty_for_missing_key(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($user)
            ->getJson('/api/help/contextual')
            ->assertOk()
            ->assertExactJson(['articles' => []]);
    }

    public function test_search_endpoint_matches_title_and_content(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        HelpArticle::create([
            'title' => 'Refunding a deposit',
            'slug' => 'refund-deposit',
            'content' => 'Approved refunds can be paid via M-Pesa B2C.',
            'category' => 'finances',
            'help_key' => null,
            'is_published' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/help/search?q=refund')
            ->assertOk()
            ->json('articles');

        $this->assertCount(1, $response);
        $this->assertSame('refund-deposit', $response[0]['slug']);
    }

    public function test_search_ignores_short_query(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($user)
            ->getJson('/api/help/search?q=a')
            ->assertOk()
            ->assertExactJson(['articles' => []]);
    }

    public function test_search_respects_role_scoping(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        HelpArticle::create([
            'title' => 'Landlord-only feature',
            'slug' => 'landlord-only',
            'content' => 'How to add a property',
            'category' => 'getting-started',
            'help_key' => null,
            'roles' => ['landlord'],
            'is_published' => true,
        ]);

        $this->actingAs($landlord)
            ->getJson('/api/help/search?q=landlord')
            ->assertOk()
            ->assertJsonCount(1, 'articles');

        $this->actingAs($tenant)
            ->getJson('/api/help/search?q=landlord')
            ->assertOk()
            ->assertJsonCount(0, 'articles');
    }

    public function test_search_requires_auth(): void
    {
        $this->getJson('/api/help/search?q=refund')->assertStatus(401);
    }

    public function test_unpublished_articles_are_excluded(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        HelpArticle::create([
            'title' => 'Draft article',
            'slug' => 'draft',
            'content' => 'unpublished body',
            'category' => 'getting-started',
            'help_key' => 'page.x',
            'is_published' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/api/help/contextual?key=page.x')
            ->assertOk()
            ->assertExactJson(['articles' => []]);
    }
}
