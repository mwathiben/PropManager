<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Phase-20 Phase 4 coverage (LOW + runbook):
 *   AUTHZ-FRONT-9: impersonation banner read-only suffix when target
 *     user is DPA-4 restricted.
 *   FRONT-UX-9: EmptyState component adoption on Invoices/Index +
 *     Admin/AuditLogs.
 *   Runbook: docs/runbooks/frontend-authz-and-ux.md documenting
 *     Phase-20 conventions.
 */
class Phase20Phase4Test extends TestCase
{
    public function test_impersonation_banner_shows_read_only_when_target_restricted(): void
    {
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString(
            'v-if="isRestricted"',
            $contents,
            'AUTHZ-FRONT-9: AuthenticatedLayout must render an isRestricted-keyed read-only indicator.',
        );
        // Phase-24 I18N-FRONT-3: the label resolves via vue-i18n now.
        // Assert the binding + that the key resolves to the Article 18
        // marker in the English bundle (Swahili equivalent is asserted
        // by Phase24CiTest key parity).
        $this->assertStringContainsString(
            "t('banner.read_only_article_18')",
            $contents,
            'AUTHZ-FRONT-9 + I18N-FRONT-3: impersonation banner must surface the read-only label via t("banner.read_only_article_18").',
        );
        $en = json_decode(file_get_contents(lang_path('en.json')), true) ?: [];
        $this->assertStringContainsString(
            'Article 18',
            (string) data_get($en, 'banner.read_only_article_18'),
            'AUTHZ-FRONT-9: lang/en.json banner.read_only_article_18 must reference Article 18 explicitly.',
        );
    }

    public function test_invoices_index_uses_empty_state_component(): void
    {
        $contents = file_get_contents(base_path('resources/js/Pages/Invoices/Index.vue'));

        $this->assertStringContainsString(
            "import EmptyState from '@/Components/EmptyState.vue'",
            $contents,
            'Invoices/Index must import EmptyState (Phase-20 FRONT-UX-9).',
        );
        $this->assertStringContainsString(
            '<EmptyState',
            $contents,
            'Invoices/Index must use <EmptyState> in template.',
        );
        $this->assertStringNotContainsString(
            '<p>No invoices found</p>',
            $contents,
            'Invoices/Index must no longer render the bare "No invoices found" paragraph.',
        );
    }

    public function test_admin_auditlogs_uses_empty_state_component(): void
    {
        $contents = file_get_contents(base_path('resources/js/Pages/Admin/AuditLogs.vue'));

        $this->assertStringContainsString(
            "import EmptyState from '@/Components/EmptyState.vue'",
            $contents,
            'Admin/AuditLogs must import EmptyState (Phase-20 FRONT-UX-9).',
        );
        $this->assertStringContainsString(
            '<EmptyState',
            $contents,
            'Admin/AuditLogs must use <EmptyState> in template.',
        );
    }

    public function test_frontend_authz_and_ux_runbook_exists(): void
    {
        $path = base_path('docs/runbooks/frontend-authz-and-ux.md');
        $this->assertFileExists($path, 'Phase-20 runbook must be shipped.');

        $contents = file_get_contents($path);
        // Spot-check key sections so a future trimming is caught.
        $this->assertStringContainsString('Inertia abilities-share contract', $contents);
        $this->assertStringContainsString('useAuth().can()', $contents);
        $this->assertStringContainsString('DPA-4 restricted-user UX', $contents);
        $this->assertStringContainsString('CursorPagination.vue', $contents);
        $this->assertStringContainsString('FormSubmitButton', $contents);
        $this->assertStringContainsString('Deferred to Phase 21', $contents);
    }
}
