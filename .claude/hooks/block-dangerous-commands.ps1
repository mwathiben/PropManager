# PreToolUse hook for Bash tool - blocks destructive commands.
# Reads JSON from stdin, exits 2 with stderr message to block.
#
# FOOTGUN-GUARD REGISTRY: this hook is the enforcement home for root-caused,
# recurring failure modes. When a class of mistake bites (and especially when it
# bites twice), encode it here as a deterministic block -- the harness runs this
# on every Bash call, across compaction and new sessions, so the guard can't be
# forgotten. A memory note alone is not a fix; the guard is. Each block below
# cites the incident it prevents.

$ErrorActionPreference = 'Stop'

try {
    $stdin = [Console]::In.ReadToEnd()
    $payload = $stdin | ConvertFrom-Json
    $command = $payload.tool_input.command
    $isBackground = [bool]$payload.tool_input.run_in_background

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

    # GUARD (incident: merge-watcher tree corruption, bit twice -- carried staged
    # files onto main + landed a stray commit; earlier auto-closed stacked PRs
    # #94/#97/#95). A backgrounded command must NEVER mutate the shared git
    # working tree: a long-lived/collapsing/overlapping watcher collides with
    # foreground edits. Branch switches and history/index mutations are the
    # danger; read-only git (status/log/fetch/gh) stays allowed.
    if ($isBackground -and ($command -match 'git\s+(checkout|switch|reset|pull|rebase|stash|cherry-pick|merge|revert|am|apply|clean)\b' -or $command -match 'git\s+branch\s+-[fDM]')) {
        [Console]::Error.WriteLine("BLOCKED: a background command must not mutate the git working tree (checkout/switch/reset/pull/rebase/merge/...). A background watcher that touches local git corrupts the shared tree. Use a gh-only watcher (gh pr checks/merge/update-branch -- no local git), or run the git step in the foreground.")
        exit 2
    }

    # GUARD (incident: background `artisan test` piped to tail crashed mid-migrate
    # and corrupted propmanager_test, producing phantom "table doesn't exist"
    # failures). Backgrounding the test runner through a pipe is unsafe.
    if ($isBackground -and $command -match 'artisan\s+test' -and $command -match '\|') {
        [Console]::Error.WriteLine("BLOCKED: do not background 'artisan test' through a pipe -- it can crash mid-migrate and corrupt propmanager_test. Run it in the foreground (e.g. --compact), or redirect to a file with no pipe.")
        exit 2
    }

    exit 0
} catch {
    # Never fail-open silently; log and let through if hook itself errors
    [Console]::Error.WriteLine("hook error in block-dangerous-commands: $_")
    exit 0
}
