<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\TenantActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-20 Phase 2 coverage (FRONT-UX HIGH severity):
 *   FRONT-UX-1 (Phase-19 INDEX-6 closure): cursor pagination on
 *     audit_logs + tenant_activities. Supporting indexes added.
 *     Controllers switched to ->cursorPaginate(). Inertia response
 *     shape switched from offset (from/to/total/links) to cursor
 *     (next_page_url/prev_page_url). New CursorPagination.vue.
 */
class Phase20FrontUxTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_audit_logs_table_has_cursor_pagination_composite_index(): void
    {
        $this->assertTrue(
            Schema::hasIndex('audit_logs', 'audit_logs_landlord_created_id_idx'),
            'Phase-20 FRONT-UX-1: audit_logs needs (landlord_id, created_at, id) composite for the cursor seek path.',
        );
    }

    public function test_tenant_activities_table_has_cursor_pagination_composite_index(): void
    {
        $this->assertTrue(
            Schema::hasIndex('tenant_activities', 'tenant_activities_landlord_created_id_idx'),
            'Phase-20 FRONT-UX-1: tenant_activities needs (landlord_id, created_at, id) composite for the cursor seek path.',
        );
    }

    public function test_audit_logs_index_returns_cursor_paginator_shape(): void
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'email_verified_at' => now(),
        ]);

        AuditLog::create([
            'event_type' => 'created',
            'description' => 'Test event',
            'auditable_type' => 'App\\Models\\Invoice',
            'auditable_id' => 1,
            'user_id' => $landlord->id,
            'landlord_id' => $landlord->id,
        ]);

        $response = $this->actingAs($landlord)->get('/audit-logs');

        $response->assertOk();
        $logs = $response->viewData('page')['props']['logs'];

        $this->assertIsArray($logs);
        $this->assertArrayHasKey('data', $logs);
        $this->assertArrayHasKey('next_page_url', $logs);
        $this->assertArrayHasKey('prev_page_url', $logs);
        $this->assertArrayHasKey('per_page', $logs);
        $this->assertArrayNotHasKey(
            'total',
            $logs,
            'Cursor paginator must NOT have a total counter (Phase-20 FRONT-UX-1 cursor shape).',
        );
        $this->assertArrayNotHasKey(
            'from',
            $logs,
            'Cursor paginator must NOT have a from counter.',
        );
        $this->assertArrayNotHasKey(
            'last_page',
            $logs,
            'Cursor paginator must NOT have a last_page counter.',
        );
    }

    public function test_audit_logs_cursor_navigates_forward_consistently(): void
    {
        // Functional cursor traversal: 26 audit logs → first page has
        // 25 results + a next_page_url; following that yields the
        // remaining 1 row + a prev_page_url + no next_page_url.
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'email_verified_at' => now(),
        ]);

        for ($i = 0; $i < 26; $i++) {
            AuditLog::create([
                'event_type' => 'created',
                'description' => 'Event '.$i,
                'auditable_type' => 'App\\Models\\Invoice',
                'auditable_id' => $i + 1,
                'user_id' => $landlord->id,
                'landlord_id' => $landlord->id,
            ]);
        }

        $response = $this->actingAs($landlord)->get('/audit-logs');
        $response->assertOk();
        $logs = $response->viewData('page')['props']['logs'];

        $this->assertCount(25, $logs['data'], 'First cursor page must return 25 rows.');
        $this->assertNotNull($logs['next_page_url'], 'next_page_url must be set when more rows exist.');
        $this->assertNull($logs['prev_page_url'], 'First page must have no prev_page_url.');
    }

    public function test_activity_logs_index_returns_cursor_paginator_shape(): void
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'email_verified_at' => now(),
        ]);

        TenantActivity::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $landlord->id,
            'type' => 'lease_created',
            'description' => 'Test activity',
            'performed_by' => $landlord->id,
        ]);

        $response = $this->actingAs($landlord)->get('/activity-logs');

        $response->assertOk();
        $activities = $response->viewData('page')['props']['activities'];

        $this->assertArrayHasKey('next_page_url', $activities);
        $this->assertArrayHasKey('prev_page_url', $activities);
        $this->assertArrayNotHasKey('total', $activities);
        $this->assertArrayNotHasKey('last_page', $activities);
    }
}
