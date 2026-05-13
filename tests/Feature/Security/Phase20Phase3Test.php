<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-20 Phase 3a coverage (MEDIUM severity):
 *   AUTHZ-FRONT-3: AuthenticatedLayout admin nav driven by can('access-admin')
 *     instead of raw role string.
 *   AUTHZ-FRONT-4: DPA-4 restricted-user banner rendered in layout
 *     when auth.user.is_restricted is true.
 *   AUTHZ-FRONT-8: Sanctum abilities (landlord:manage, tenant:read,
 *     admin:all) mirrored in Gate registry alongside Phase-19's
 *     integration:webhook.
 *   FRONT-UX-3: InputError.vue enhanced (assertion lives in the
 *     Vue layer; covered by visual review + the existing component
 *     test patterns).
 *   FRONT-UX-7: useFocusTrap composable wired into Modal.vue
 *     (Vue-layer behavior, not server-testable; covered by visual
 *     check + a smoke test that the import wiring is present).
 *   FRONT-UX-8: Badge.vue text shades bumped to 900-weight for AA
 *     contrast (Vue-layer; covered by file-content assertion).
 */
class Phase20Phase3Test extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /** @return array<int, array{0: string}> */
    public static function sanctumAbilityProvider(): array
    {
        return [
            ['integration:webhook'],
            ['landlord:manage'],
            ['tenant:read'],
            ['admin:all'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sanctumAbilityProvider')]
    public function test_sanctum_ability_is_mirrored_in_gate_registry(string $ability): void
    {
        $this->assertTrue(
            Gate::has($ability),
            "Phase-20 AUTHZ-FRONT-8: '{$ability}' Sanctum ability must have a Gate::define mirror so DPA-4 restriction applies.",
        );
    }

    public function test_authenticated_layout_uses_can_access_admin_not_raw_role(): void
    {
        // AUTHZ-FRONT-3 structural: the admin nav branch must consult
        // can('access-admin') from the abilities map, not a raw role
        // string. Phase-18 no-raw-role watchdog spirit applied to the
        // client side.
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString(
            "can('access-admin')",
            $contents,
            'AuthenticatedLayout must gate the admin nav via can(\'access-admin\') (Phase-20 AUTHZ-FRONT-3).',
        );

        // The old role-string branch for super_admin must be gone.
        $this->assertDoesNotMatchRegularExpression(
            "/role\\s*===\\s*['\"]super_admin['\"]/m",
            // strip the roleConfig record where the literal is a map key,
            // not a comparison.
            (string) preg_replace('/roleConfig\s*=\s*computed.*?\}\);/s', '', $contents),
            'AuthenticatedLayout must not use raw `role === \'super_admin\'` comparisons outside the roleConfig map (Phase-20 AUTHZ-FRONT-3).',
        );
    }

    public function test_authenticated_layout_renders_dpa4_restricted_banner_block(): void
    {
        // AUTHZ-FRONT-4 structural: the layout has a banner block keyed
        // on isRestricted. The full visual test is a Vue-layer concern;
        // server-side we pin the markup presence so a future removal
        // is caught.
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString(
            'v-if="isRestricted"',
            $contents,
            'AuthenticatedLayout must render a banner block when isRestricted (Phase-20 AUTHZ-FRONT-4).',
        );

        $this->assertStringContainsString(
            'Article 18',
            $contents,
            'AUTHZ-FRONT-4 banner must reference the legal basis (Article 18 / Kenya DPA Section 26(d)).',
        );
    }

    public function test_input_error_component_uses_role_alert_and_strong_contrast(): void
    {
        // FRONT-UX-3: InputError now has role="alert" + icon + text-red-700.
        $contents = file_get_contents(base_path('resources/js/Components/InputError.vue'));

        $this->assertStringContainsString('role="alert"', $contents, 'InputError must declare role="alert" for screen readers.');
        $this->assertStringContainsString('ExclamationCircleIcon', $contents, 'InputError must include an icon (color-blind affordance).');
        $this->assertStringContainsString('text-red-700', $contents, 'InputError must use text-red-700 for AA contrast (was text-red-600).');
        // The string 'text-red-600' may appear in PHPDoc historical
        // commentary; assert it's absent from the <p class="..."> tag
        // and the icon class binding by inspecting the template block.
        $template = preg_replace('/^.*?<template>/s', '', $contents);
        $this->assertStringNotContainsString(
            'text-red-600',
            (string) $template,
            'InputError template must no longer use text-red-600 (Phase-20 FRONT-UX-3 contrast bump).',
        );
    }

    public function test_modal_uses_focus_trap(): void
    {
        // FRONT-UX-7: Modal imports + wires useFocusTrap.
        $contents = file_get_contents(base_path('resources/js/Components/Modal.vue'));

        $this->assertStringContainsString('useFocusTrap', $contents, 'Modal must import + use useFocusTrap (Phase-20 FRONT-UX-7).');
        $this->assertFileExists(
            base_path('resources/js/composables/useFocusTrap.ts'),
            'useFocusTrap composable file must exist.',
        );
    }

    public function test_badge_component_uses_900_weight_for_wcag_aa_contrast(): void
    {
        // FRONT-UX-8: Badge colorMap shifted from -800 → -900 text shades.
        $contents = file_get_contents(base_path('resources/js/Components/Badge.vue'));

        $this->assertStringContainsString('text-yellow-900', $contents, 'Yellow badge must use yellow-900 text (was yellow-800; AA contrast bump).');
        $this->assertStringContainsString('text-gray-900', $contents);
        $this->assertStringContainsString('text-red-900', $contents);
        $this->assertStringNotContainsString(
            'text-yellow-800',
            $contents,
            'Badge must no longer use yellow-800 text — fails AA contrast at 14px (Phase-20 FRONT-UX-8).',
        );
    }
}
