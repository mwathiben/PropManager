# SessionStart hook — surface recent activity and any failing tests when a session begins.
# Output to stdout is shown to the model as additional system context.

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
    Write-Output "Open PRs:"
    $prs = gh pr list --limit 5 2>$null
    if ($LASTEXITCODE -eq 0 -and $prs) {
        $prs | ForEach-Object { Write-Output "  $_" }
    } else {
        Write-Output "  (gh not configured or no open PRs)"
    }

    Write-Output ""
    Write-Output "=== end session start context ==="
    exit 0
} catch {
    exit 0
}
