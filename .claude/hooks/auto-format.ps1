# PostToolUse hook for Edit/Write — auto-format the file that was just touched.
# Best-effort; failures are silent.

$ErrorActionPreference = 'Continue'

try {
    $input = [Console]::In.ReadToEnd()
    $payload = $input | ConvertFrom-Json
    $filePath = $payload.tool_input.file_path

    if (-not $filePath -or -not (Test-Path -LiteralPath $filePath)) {
        exit 0
    }

    $ext = [System.IO.Path]::GetExtension($filePath).ToLower()

    switch ($ext) {
        '.php' {
            # Pint formats per PropManager's pint.json config.
            # MEMORY.md notes: use `php vendor/bin/pint`, not `./vendor/bin/pint` on Windows.
            if (Test-Path 'vendor/bin/pint') {
                & php 'vendor/bin/pint' $filePath 2>$null | Out-Null
            }
        }
        { $_ -in '.vue', '.js', '.ts', '.jsx', '.tsx' } {
            if (Test-Path 'node_modules/.bin/prettier.cmd') {
                & 'node_modules/.bin/prettier.cmd' --write $filePath --log-level=silent 2>$null | Out-Null
            }
        }
        '.json' {
            # leave json alone — formatting may cause unwanted diffs in config files
        }
    }

    exit 0
} catch {
    exit 0
}
