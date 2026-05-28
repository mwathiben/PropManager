# SessionStart hook - surface recent local activity when a session begins.
# Output to stdout is shown to the model as additional system context.
#
# Local-only by design: per .claude/CLAUDE.md ("Hooks should not exfiltrate
# data or make external network calls"), this hook reads only the local
# git index. Open PR / GitHub state should be fetched on demand by the
# agent through the user-authorized github MCP, not auto-pulled here.

$ErrorActionPreference = 'SilentlyContinue'

try {
    Write-Output "=== PropManager session start ==="

    Write-Output ""
    Write-Output "Recent commits (last 3 days):"
    $recent = git log --since='3 days ago' --oneline -n 10 2>$null
    if ($recent) {
        $recent | ForEach-Object { Write-Output "  $_" }
    } else {
        Write-Output "  (no recent commits)"
    }

    Write-Output ""
    Write-Output "Working tree:"
    $status = git status --short 2>$null
    if ($status) {
        $status | Select-Object -First 15 | ForEach-Object { Write-Output "  $_" }
    } else {
        Write-Output "  (clean)"
    }

    Write-Output ""
    Write-Output "Current branch: $(git rev-parse --abbrev-ref HEAD 2>$null)"

    Write-Output ""
    Write-Output "=== end session start context ==="
    exit 0
} catch {
    exit 0
}
