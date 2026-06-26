<?php

declare(strict_types=1);

/*
 * Regenerate the shrink-only PHPMD baseline RELIABLY.
 *
 * PDepend's `~/.pdepend` analysis cache does not reliably invalidate on edit in
 * this project — it once produced a stale "581 -> 580" read after 9 files were
 * already fixed, and reported a real 82-line LongMethod as "clean". Clearing the
 * cache before `--generate-baseline` guarantees the baseline reflects the CURRENT
 * code, so we never commit an inaccurate baseline that would turn a fresh CI run
 * red (CI uses --baseline-file against a clean checkout).
 *
 * Run via:  composer phpmd:baseline
 * Then REVIEW the phpmd.baseline.xml diff — it must only ever SHRINK.
 */

$home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();
$cache = rtrim(str_replace('\\', '/', $home), '/').'/.pdepend';

if (is_dir($cache)) {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($cache);
    fwrite(STDERR, "Cleared PDepend cache: {$cache}\n");
}

passthru(escapeshellarg(PHP_BINARY).' vendor/bin/phpmd app text phpmd.xml --generate-baseline 2>'.(DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null'));

fwrite(STDERR, "Baseline regenerated. Review `git diff phpmd.baseline.xml` — it must only SHRINK.\n");
