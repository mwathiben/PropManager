<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Http\Middleware\HandleInertiaRequests;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression: vue-i18n parses '@' as its linked-message operator, so a literal
 * '@' in a translation (e.g. the email placeholder 'tenant@example.com') makes
 * the client message compiler throw a SyntaxError the instant that string is
 * rendered — silently breaking the component (it surfaced as the onboarding
 * wizard blanking when the team / first-tenant email inputs rendered). The app
 * uses no '@:'-linked messages, so the i18n bundle escapes every literal '@' as
 * {'@'} when building the client payload, while the PHP lang files keep raw '@'
 * for Blade trans().
 */
class VueI18nAtSymbolEscapeTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function bundle(): array
    {
        $middleware = app(HandleInertiaRequests::class);
        $method = new ReflectionMethod($middleware, 'getI18nBundle');

        return $method->invoke($middleware);
    }

    public function test_known_email_placeholders_are_escaped_for_vue_i18n(): void
    {
        $bundle = $this->bundle();

        $this->assertSame(
            "caretaker{'@'}example.com",
            data_get($bundle, 'onboarding.page.team.email_placeholder'),
        );
        $this->assertSame(
            "tenant{'@'}example.com",
            data_get($bundle, 'onboarding.page.first_tenant.tenant_email_placeholder'),
        );
    }

    public function test_no_bundle_string_carries_an_unescaped_at_symbol(): void
    {
        $offenders = [];

        $walk = function (array $node, string $path) use (&$walk, &$offenders): void {
            foreach ($node as $key => $value) {
                $childPath = $path === '' ? (string) $key : "{$path}.{$key}";

                if (is_array($value)) {
                    $walk($value, $childPath);
                } elseif (is_string($value) && str_contains(str_replace("{'@'}", '', $value), '@')) {
                    $offenders[] = $childPath;
                }
            }
        };

        $walk($this->bundle(), '');

        $this->assertSame(
            [],
            $offenders,
            'Every literal @ in a vue-i18n message must be escaped as {\'@\'}; unescaped at: '.implode(', ', $offenders),
        );
    }

    public function test_raw_php_lang_file_keeps_unescaped_at_for_blade(): void
    {
        // The escape is applied only to the client bundle — Blade trans() reads
        // the raw lang file, which must still contain a plain '@'.
        $lang = require lang_path('en/onboarding.php');

        $this->assertSame('caretaker@example.com', data_get($lang, 'page.team.email_placeholder'));
    }
}
