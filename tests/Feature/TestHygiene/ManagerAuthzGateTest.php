<?php

declare(strict_types=1);

namespace Tests\Feature\TestHygiene;

use Tests\TestCase;

/**
 * MANAGER-AUTHZ-1 guardrail. A `manager` is a full scope owner (landlord_id ==
 * its own id), so wherever a landlord is authorized, a manager must be too.
 * Authorization gates — in FormRequests AND Policies — must therefore use
 * isScopeOwner(), never isLandlord(); the latter silently 403s the entire
 * manager role (this is what hid the water-module bug). Deterministic watchdog:
 * it fails CI the moment a new gate uses isLandlord(), so the systemic gap that
 * spanned ~60 FormRequests + 40 Policies cannot regress.
 *
 * The ONLY escape hatch is INTENTIONALLY_LANDLORD_ONLY, which requires a
 * concrete justification and is reviewed.
 */
class ManagerAuthzGateTest extends TestCase
{
    /**
     * Files where isLandlord() is intentional and a manager must NOT pass. Keep
     * empty unless there is a real reason a scope-owner manager is forbidden.
     *
     * @var list<string>
     */
    private const INTENTIONALLY_LANDLORD_ONLY = [
        // (none) — managers are full scope owners; every landlord gate admits them.
    ];

    public function test_no_form_request_gates_on_is_landlord(): void
    {
        $this->assertNoIsLandlordIn(app_path('Http/Requests'), 'FormRequest');
    }

    public function test_no_policy_gates_on_is_landlord(): void
    {
        $this->assertNoIsLandlordIn(app_path('Policies'), 'Policy');
    }

    private function assertNoIsLandlordIn(string $dir, string $kind): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([$dir.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $file->getPathname());

            if (in_array($relative, self::INTENTIONALLY_LANDLORD_ONLY, true)) {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), 'isLandlord(')) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "{$kind} authorization must use isScopeOwner(), not isLandlord() — these lock out the manager role:\n  - ".implode("\n  - ", $offenders),
        );
    }
}
