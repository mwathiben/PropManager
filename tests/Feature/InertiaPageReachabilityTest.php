<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guard against the class of bug behind the 2026-05-21 "buttons don't open
 * pages" report: a GET route whose controller renders an Inertia component
 * that doesn't exist (vite build + PHPUnit can't catch it, so it 404s only
 * at runtime). For every GET route bound to a controller method, resolve the
 * literal Inertia::render('X') target and assert resources/js/Pages/X.vue
 * exists.
 */
class InertiaPageReachabilityTest extends TestCase
{
    public function test_every_routed_inertia_page_exists(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue; // closure / redirect route
            }

            [$class, $method] = explode('@', $action, 2);
            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }

            $target = $this->renderTargetFor($class, $method);
            if ($target === null) {
                continue; // no literal render target (dynamic or non-Inertia)
            }

            $vue = resource_path('js/Pages/'.$target.'.vue');
            if (! file_exists($vue)) {
                $name = $route->getName() ?? $route->uri();
                $missing[] = "{$name} ({$route->uri()}) -> {$target}";
            }
        }

        $this->assertSame(
            [],
            $missing,
            "These GET routes render Inertia pages that don't exist (would 404 on navigation):\n".implode("\n", $missing),
        );
    }

    private function renderTargetFor(string $class, string $method): ?string
    {
        $ref = new ReflectionMethod($class, $method);
        $file = $ref->getFileName();
        if ($file === false) {
            return null;
        }

        $lines = file($file);
        if ($lines === false) {
            return null;
        }

        $body = implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1,
        ));

        if (preg_match('/(?:Inertia::render|->render)\(\s*[\'"]([A-Za-z0-9_\/.-]+)[\'"]/', $body, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
