<?php

declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;

/**
 * Phase-23 A11Y-SR-1: ARIA live announcer watchdog (WCAG 4.1.3
 * Status Messages). Pins that the announcer component carries both
 * politeness regions and that BOTH layouts mount it + wire Inertia
 * flash into it.
 */
class Phase23SrTest extends TestCase
{
    public function test_announcer_has_polite_and_assertive_regions(): void
    {
        $path = resource_path('js/Components/LiveAnnouncer.vue');
        $this->assertFileExists($path, 'A11Y-SR-1: LiveAnnouncer.vue must exist.');

        $component = file_get_contents($path);
        $this->assertStringContainsString(
            'aria-live="polite"',
            $component,
            'A11Y-SR-1: LiveAnnouncer must render an aria-live="polite" region.',
        );
        $this->assertStringContainsString(
            'aria-live="assertive"',
            $component,
            'A11Y-SR-1: LiveAnnouncer must render an aria-live="assertive" region.',
        );
        $this->assertStringContainsString(
            'role="alert"',
            $component,
            'A11Y-SR-1: the assertive region must carry role="alert".',
        );
        $this->assertStringContainsString(
            'sr-only',
            $component,
            'A11Y-SR-1: the announcer must be visually hidden (sr-only).',
        );
    }

    public function test_announcer_composable_exposes_announce(): void
    {
        $path = resource_path('js/composables/useAnnouncer.ts');
        $this->assertFileExists($path, 'A11Y-SR-1: useAnnouncer.ts must exist.');

        $composable = file_get_contents($path);
        $this->assertStringContainsString(
            'function announce(',
            $composable,
            'A11Y-SR-1: useAnnouncer must expose an announce() function.',
        );
        $this->assertStringContainsString(
            "politeness: Politeness = 'polite'",
            $composable,
            'A11Y-SR-1: announce() must accept a politeness argument defaulting to polite.',
        );
    }

    public function test_layouts_mount_live_announcer(): void
    {
        foreach (['AuthenticatedLayout', 'GuestLayout'] as $layout) {
            $contents = file_get_contents(resource_path("js/Layouts/{$layout}.vue"));

            $this->assertStringContainsString(
                'LiveAnnouncer',
                $contents,
                "A11Y-SR-1: {$layout} must import + mount LiveAnnouncer.",
            );
            $this->assertStringContainsString(
                'useAnnouncer',
                $contents,
                "A11Y-SR-1: {$layout} must use the announcer composable.",
            );
            $this->assertStringContainsString(
                'page.props.flash',
                $contents,
                "A11Y-SR-1: {$layout} must watch Inertia flash props and announce them.",
            );
        }
    }

    public function test_inertia_middleware_shares_flash(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

        $this->assertStringContainsString(
            "'flash'",
            $middleware,
            'A11Y-SR-1: HandleInertiaRequests must share a flash prop for the announcer.',
        );
        foreach (['success', 'error', 'message'] as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $middleware,
                "A11Y-SR-1: the shared flash prop must expose '{$key}'.",
            );
        }
    }

    public function test_nav_landmarks_are_labelled(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));

        // Phase-24 I18N-FRONT-3: nav aria-labels now resolve via
        // vue-i18n (the screen-reader announces them in the user's
        // locale). Assert the binding + that the key resolves in
        // every supported bundle.
        foreach (['nav.primary_label', 'nav.mobile_primary_label'] as $key) {
            $this->assertStringContainsString(
                "t('{$key}')",
                $layout,
                "A11Y-SR-2 + I18N-FRONT-3: AuthenticatedLayout's <nav> landmarks must bind aria-label to t('{$key}').",
            );
            foreach (array_keys(config('app.available_locales')) as $locale) {
                $bundle = json_decode(file_get_contents(lang_path("{$locale}.json")), true) ?: [];
                $this->assertNotSame(
                    null,
                    data_get($bundle, $key),
                    "A11Y-SR-2: lang/{$locale}.json must define '{$key}'.",
                );
            }
        }

        $breadcrumb = file_get_contents(resource_path('js/Components/Breadcrumb.vue'));
        $this->assertStringContainsString(
            'aria-label="Breadcrumb"',
            $breadcrumb,
            'A11Y-SR-2: the Breadcrumb <nav> must carry aria-label="Breadcrumb".',
        );
    }

    /**
     * A11Y-SR-2: every Inertia page must contribute an <h1> to the
     * document outline. The allow-list covers files under resources/
     * js/Pages that are NOT standalone pages — tab panels, modals,
     * partials and sub-component directories render inside a parent
     * page that already owns the <h1>. Welcome.vue is the full-bleed
     * marketing splash. This list is shrink-only: removing an entry
     * (because the file gained a real <h1>) is fine; adding one needs
     * a justification here.
     */
    public function test_every_page_has_an_h1(): void
    {
        $allowedPatterns = [
            '#/(tabs|modals|components)/#i',
            '#/Partials/#',
            '#Tab\.vue$#',
            '#Modal\.vue$#',
            '#/Welcome\.vue$#',
        ];

        // Phase-74 design-system scaffolds render the page <h1> themselves
        // (HubShell.vue + CenterHero.vue each emit `<h1>{{ title }}</h1>`), so a page that
        // delegates its heading to one of these owns an <h1> at runtime even without a
        // literal tag in its own file.
        $scaffoldsProvidingH1 = ['<HubShell', '<CenterHero'];

        $pagesDir = resource_path('js/Pages');
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pagesDir, \FilesystemIterator::SKIP_DOTS),
        );

        $missing = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());

            $allowed = false;
            foreach ($allowedPatterns as $pattern) {
                if (preg_match($pattern, $path)) {
                    $allowed = true;
                    break;
                }
            }
            if ($allowed) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            $hasH1 = str_contains($contents, '<h1');
            $usesScaffold = false;
            foreach ($scaffoldsProvidingH1 as $scaffold) {
                if (str_contains($contents, $scaffold)) {
                    $usesScaffold = true;
                    break;
                }
            }

            if (! $hasH1 && ! $usesScaffold) {
                $missing[] = $path;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'A11Y-SR-2: these standalone pages have no <h1> — add one (sr-only is fine) '
                ."or, if it is genuinely not a page, allow-list it:\n".implode("\n", $missing),
        );
    }

    public function test_datatable_exposes_table_semantics(): void
    {
        foreach (['DataTable', 'VirtualDataTable'] as $component) {
            $contents = file_get_contents(resource_path("js/Components/Finances/{$component}.vue"));

            $this->assertStringContainsString(
                '<caption v-if="caption" class="sr-only">',
                $contents,
                "A11Y-SR-3: {$component} must render a <caption> when a caption prop is given.",
            );
            $this->assertStringContainsString(
                'scope="col"',
                $contents,
                "A11Y-SR-3: {$component} header cells must carry scope=\"col\".",
            );
            $this->assertStringContainsString(
                ':aria-sort="ariaSortFor(column)"',
                $contents,
                "A11Y-SR-3: {$component} must reflect sort state via aria-sort.",
            );
            $this->assertMatchesRegularExpression(
                '/<button\s+v-if="column\.sortable"\s+type="button"/',
                $contents,
                "A11Y-SR-3: {$component} sortable columns must use a real <button>, not a click-on-<th>.",
            );
        }
    }

    public function test_building_map_has_a_text_alternative(): void
    {
        $map = file_get_contents(resource_path('js/Components/BuildingMap.vue'));

        $this->assertStringContainsString(
            'aria-hidden="true"',
            $map,
            'A11Y-SR-4: the Leaflet map container must be aria-hidden — the address is shown as text elsewhere.',
        );
        $this->assertStringContainsString(
            'role="presentation"',
            $map,
            'A11Y-SR-4: the map container must carry role="presentation".',
        );
        $this->assertStringContainsString(
            'aria-label="Open location in Google Maps"',
            $map,
            'A11Y-SR-4: the Google Maps action button must have an accessible name.',
        );
    }
}
