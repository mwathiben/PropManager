# PreToolUse hook for Edit/Write — blocks edits to protected files
# Reads JSON from stdin, exits 2 with stderr message to block.

$ErrorActionPreference = 'Stop'

try {
    $input = [Console]::In.ReadToEnd()
    $payload = $input | ConvertFrom-Json
    $filePath = $payload.tool_input.file_path

    if (-not $filePath) { exit 0 }

    # Normalize to forward slashes for matching
    $normalized = $filePath -replace '\\', '/'

    # Protected paths — edits require explicit human action
    $protected = @(
        '\.env$',
        '\.env\.production',
        '\.env\.staging',
        '/secrets/',
        '/credentials/',
        '/vendor/',
        '/node_modules/',
        '/public/build/',
        '/storage/app/private/'
    )

    foreach ($pattern in $protected) {
        if ($normalized -match $pattern) {
            [Console]::Error.WriteLine("BLOCKED: '$filePath' is protected. If this edit is intentional, make it manually outside the agent.")
            exit 2
        }
    }

    exit 0
} catch {
    [Console]::Error.WriteLine("hook error in check-not-protected: $_")
    exit 0
}
