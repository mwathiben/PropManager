<?php

declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;

/**
 * Phase-23 A11Y-VISUAL-1 + A11Y-VISUAL-2 watchdogs (WCAG 2.3.3,
 * 1.4.1). Source-level assertions in the Phase-22 watchdog style.
 */
class Phase23VisualTest extends TestCase
{
    public function test_app_css_honours_prefers_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(
            '@media (prefers-reduced-motion: reduce)',
            $css,
            'A11Y-VISUAL-1: app.css must carry a prefers-reduced-motion media query.',
        );
        $this->assertStringContainsString(
            'transition-duration: 0.01ms !important',
            $css,
            'A11Y-VISUAL-1: the reduced-motion rule must neutralise transition durations.',
        );
        $this->assertStringContainsString(
            'animation-duration: 0.01ms !important',
            $css,
            'A11Y-VISUAL-1: the reduced-motion rule must neutralise animation durations.',
        );
    }

    /**
     * A11Y-VISUAL-2: status badges must never convey state by colour
     * alone — each pairs its colour with a text label (and KycBadge
     * additionally with an icon). The ad-hoc coloured-cell audit of
     * the index pages is recorded in docs/runbooks/accessibility.md.
     */
    public function test_status_badges_pair_colour_with_text(): void
    {
        $badges = [
            'Components/Finances/InvoiceStatusBadge.vue',
            'Components/TicketStatusBadge.vue',
            'Components/TicketPriorityBadge.vue',
            'Components/KycBadge.vue',
        ];

        foreach ($badges as $badge) {
            $contents = file_get_contents(resource_path("js/{$badge}"));
            $this->assertStringContainsString(
                ':label="label"',
                $contents,
                "A11Y-VISUAL-2: {$badge} must pass a text :label to Badge — colour is never the sole cue.",
            );
        }
    }
}
