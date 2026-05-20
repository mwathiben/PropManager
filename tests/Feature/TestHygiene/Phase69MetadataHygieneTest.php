<?php

declare(strict_types=1);

namespace Tests\Feature\TestHygiene;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Phase-69 DEPRECATION-GATE-2: a static guard that no test reintroduces
 * doc-comment PHPUnit metadata (deprecated in PHPUnit 11, removed in 12).
 * Complements the runtime failOnPhpunitDeprecation gate in phpunit.xml —
 * this fails fast with the exact offending file:line even on a toolchain
 * that hasn't surfaced the deprecation yet.
 */
class Phase69MetadataHygieneTest extends TestCase
{
    /**
     * Doc-comment metadata annotations PHPUnit now wants as attributes.
     *
     * Matched ONLY in a doc-comment context (` * @annotation`) so literals
     * like `'email' => 'tenant@test.com'` are not false positives.
     */
    private const FORBIDDEN = [
        'test', 'dataProvider', 'depends', 'group', 'covers', 'coversNothing',
        'coversDefaultClass', 'uses', 'testWith', 'backupGlobals', 'backupStaticAttributes',
        'runInSeparateProcess', 'runTestsInSeparateProcesses', 'preserveGlobalState',
        'requires', 'small', 'medium', 'large', 'ticket', 'testdox',
    ];

    public function test_no_doc_comment_phpunit_metadata_remains(): void
    {
        $pattern = '/^\s*\*\s*@('.implode('|', self::FORBIDDEN).')\b/';
        $offenders = [];

        foreach (File::allFiles(base_path('tests')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (preg_split('/\R/', (string) file_get_contents($file->getPathname())) as $i => $line) {
                if (preg_match($pattern, $line)) {
                    $offenders[] = 'tests/'.ltrim(str_replace('\\', '/', $file->getRelativePathname()), '/').':'.($i + 1).' -> '.trim($line);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Doc-comment PHPUnit metadata is deprecated — use PHP 8 attributes (#[Test], #[DataProvider], #[Group], ...):\n".implode("\n", $offenders),
        );
    }
}
