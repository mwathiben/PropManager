<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase-74 design-system guard: every screen of an archetype must render
 * through the archetype's shared scaffold so the chrome stays consistent
 * with its reference. See docs/design-standards.md.
 */
class Phase74UiArchetypeTest extends TestCase
{
    private function pages(string $relative): string
    {
        return file_get_contents(base_path('resources/js/'.$relative));
    }

    /** Every tab-shell hub renders through HubShell. */
    public function test_hubs_use_hub_shell(): void
    {
        foreach ([
            'Pages/Operations/Hub.vue',
            'Pages/Maintenance/Hub.vue',
            'Pages/Water/Hub.vue',
            'Pages/Archive/Hub.vue',
            'Pages/Tenants/Hub.vue',
        ] as $hub) {
            $this->assertStringContainsString(
                'Components/Hub/HubShell.vue',
                $this->pages($hub),
                "{$hub} must render through the shared HubShell (design-standards.md).",
            );
        }
    }

    /** The shared HubShell exists. */
    public function test_hub_shell_component_exists(): void
    {
        $this->assertFileExists(base_path('resources/js/Components/Hub/HubShell.vue'));
    }

    /** Centers lead with the shared CenterHero masthead. */
    public function test_centers_use_center_hero(): void
    {
        foreach ([
            'Pages/Notifications/Index.vue',
            'Pages/LegalHolds/Home.vue',
        ] as $center) {
            $this->assertStringContainsString(
                'Components/Center/CenterHero.vue',
                $this->pages($center),
                "{$center} must lead with the shared CenterHero (design-standards.md).",
            );
        }
        $this->assertFileExists(base_path('resources/js/Components/Center/CenterHero.vue'));
    }

    /** Page wizards render progress through the shared WizardSteps. */
    public function test_wizards_use_wizard_steps(): void
    {
        foreach ([
            'Pages/LegalHolds/Wizard.vue',
            'Pages/Onboarding/Components/WizardProgressBar.vue',
        ] as $wizard) {
            $this->assertStringContainsString(
                'Components/Wizard/WizardSteps.vue',
                $this->pages($wizard),
                "{$wizard} must use the shared WizardSteps (design-standards.md).",
            );
        }
        $this->assertFileExists(base_path('resources/js/Components/Wizard/WizardSteps.vue'));
    }

    /** The shared scaffolds + the standards doc are present. */
    public function test_design_standards_doc_documents_the_archetypes(): void
    {
        $doc = file_get_contents(base_path('docs/design-standards.md'));
        foreach (['HubShell', 'CenterHero', 'WizardSteps'] as $token) {
            $this->assertStringContainsString($token, $doc);
        }
    }
}
