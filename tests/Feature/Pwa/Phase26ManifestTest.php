<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-26 PWA-MANIFEST-1 / 2 / 3 watchdogs: ensure the Web App
 * Manifest is reachable + complete, the icon set exists at every
 * size the manifest declares, and the iOS-specific meta tags are
 * present in the root blade (iOS Safari ignores the manifest icons).
 */
class Phase26ManifestTest extends TestCase
{
    public function test_manifest_is_served(): void
    {
        $path = public_path('manifest.json');
        $this->assertFileExists($path, 'PWA-MANIFEST-1: public/manifest.json must exist.');

        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($manifest, 'PWA-MANIFEST-1: manifest.json must be valid JSON.');
    }

    public function test_manifest_declares_required_fields(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true);

        foreach (['name', 'short_name', 'start_url', 'scope', 'display', 'theme_color', 'background_color', 'icons'] as $field) {
            $this->assertArrayHasKey($field, $manifest, "PWA-MANIFEST-1: manifest must declare {$field}.");
        }

        $this->assertSame('standalone', $manifest['display'], 'PWA-MANIFEST-1: display must be standalone for installability.');
        $this->assertNotEmpty($manifest['icons'], 'PWA-MANIFEST-1: manifest must declare at least one icon.');
    }

    public function test_manifest_icons_include_192_512_and_maskable(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true);
        $sizes = array_column($manifest['icons'], 'sizes');
        $purposes = array_column($manifest['icons'], 'purpose');

        $this->assertContains('192x192', $sizes, 'PWA-MANIFEST-2: manifest must declare a 192x192 icon.');
        $this->assertContains('512x512', $sizes, 'PWA-MANIFEST-2: manifest must declare a 512x512 icon.');
        $this->assertContains('maskable', $purposes, 'PWA-MANIFEST-2: manifest must declare at least one maskable icon for Android adaptive launchers.');
    }

    public function test_icon_files_exist_and_are_png(): void
    {
        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable.png', 'apple-touch-icon.png', 'badge-72.png'] as $name) {
            $path = public_path('images/'.$name);
            $this->assertFileExists($path, "PWA-MANIFEST-2: public/images/{$name} must exist.");
            $info = @getimagesize($path);
            $this->assertNotFalse($info, "PWA-MANIFEST-2: {$name} must be a readable image.");
            $this->assertSame('image/png', $info['mime'], "PWA-MANIFEST-2: {$name} must be PNG.");
        }
    }

    public function test_icon_dimensions_match_their_names(): void
    {
        $expectations = [
            'icon-192.png' => [192, 192],
            'icon-512.png' => [512, 512],
            'icon-maskable.png' => [512, 512],
            'apple-touch-icon.png' => [180, 180],
            'badge-72.png' => [72, 72],
        ];

        foreach ($expectations as $name => [$w, $h]) {
            $info = getimagesize(public_path('images/'.$name));
            $this->assertSame($w, $info[0], "PWA-MANIFEST-2: {$name} must be {$w}px wide.");
            $this->assertSame($h, $info[1], "PWA-MANIFEST-2: {$name} must be {$h}px tall.");
        }
    }

    public function test_app_blade_links_manifest_and_declares_theme_color(): void
    {
        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString(
            '<link rel="manifest"',
            $blade,
            'PWA-MANIFEST-1: app.blade.php must <link rel="manifest"> so the browser discovers the manifest.',
        );
        $this->assertStringContainsString(
            'name="theme-color"',
            $blade,
            'PWA-MANIFEST-1: app.blade.php must declare theme-color so the browser chrome matches the brand.',
        );
    }

    public function test_app_blade_carries_apple_touch_meta(): void
    {
        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString(
            'rel="apple-touch-icon"',
            $blade,
            'PWA-MANIFEST-3: iOS Safari requires apple-touch-icon at the document root for Add-to-Home-Screen.',
        );
        $this->assertStringContainsString(
            'name="apple-mobile-web-app-capable"',
            $blade,
            'PWA-MANIFEST-3: apple-mobile-web-app-capable enables iOS standalone mode.',
        );
        $this->assertStringContainsString(
            'name="apple-mobile-web-app-title"',
            $blade,
            'PWA-MANIFEST-3: apple-mobile-web-app-title sets the Add-to-Home-Screen label on iOS.',
        );
    }
}
