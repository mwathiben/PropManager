<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use Tests\TestCase;

/**
 * Slice-2 PR-2.2 source contract: the composer must surface server validation
 * per-field (no silent 422) and resolve copy via vue-i18n — the recurring
 * footguns this project has been bitten by. Client-side Inertia state the PHP
 * layer can't observe, so asserted on the Vue source.
 */
class ComposerSurfaceTest extends TestCase
{
    private function composer(): string
    {
        return (string) file_get_contents(resource_path('js/Pages/Agreements/Compose.vue'));
    }

    public function test_composer_surfaces_field_errors(): void
    {
        $source = $this->composer();

        $this->assertStringContainsString('InputError', $source);
        $this->assertStringContainsString('form.errors.property_owner_id', $source);
        $this->assertStringContainsString('form.errors.clauses', $source);
    }

    public function test_composer_posts_to_the_store_route_and_uses_i18n(): void
    {
        $source = $this->composer();

        $this->assertStringContainsString("route('agreements.store')", $source);
        $this->assertMatchesRegularExpression("/t\\(['\"]agreements\\./", $source);
    }
}
