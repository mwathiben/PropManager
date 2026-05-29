# PreToolUse hook for Bash tool - blocks destructive commands.
# Reads JSON from stdin, exits 2 with stderr message to block.

$ErrorActionPreference = 'Stop'

try {
    $stdin = [Console]::In.ReadToEnd()
    $payload = $stdin | ConvertFrom-Json
    $command = $payload.tool_input.command

    if (-not $command) { exit 0 }

    # Hard-deny patterns
    $denyPatterns = @(
        'rm\s+-rf\s+/',
        'rm\s+-rf\s+~',
        'rm\s+-rf\s+\*',
        'DROP\s+DATABASE',
        'DROP\s+TABLE',
        'TRUNCATE\s+TABLE',
        'TRUNCATE\s+`',
        'git\s+push\s+--force(?!-with-lease)',
        'git\s+reset\s+--hard\s+origin',
        '>\s*\.env\b',
        '>\s*\.env\.production',
        'composer\s+global\s+remove',
        'npm\s+publish'
    )

    foreach ($pattern in $denyPatterns) {
        if ($command -match $pattern) {
            [Console]::Error.WriteLine("BLOCKED: command matches deny pattern '$pattern'. If intentional, run it manually outside the agent.")
            exit 2
        }
    }

    # Production deployment guard
    if ($command -match '(forge|envoy|wrangler)\s+deploy.*production') {
        [Console]::Error.WriteLine("BLOCKED: production deployment requires explicit human action - run manually.")
        exit 2
    }

    exit 0
} catch {
    # Never fail-open silently; log and let through if hook itself errors
    [Console]::Error.WriteLine("hook error in block-dangerous-commands: $_")
    exit 0
}
