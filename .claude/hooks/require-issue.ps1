# PreToolUse hook (Edit|Write): nudge Claude to track work in an issue before
# editing functional files. Windows/PowerShell port of the tracker's
# require-issue.sh (Claude Project Tracker). Reads the tool payload from STDIN
# (this Claude Code build feeds hooks JSON on stdin, not via env vars).
#
# Exit 0 = allow, Exit 2 = block with a reminder on stderr.
# FAIL-OPEN: any error -> exit 0. A workflow nudge must never break editing.

try {
    $stdin = [Console]::In.ReadToEnd()
    if (-not $stdin) { exit 0 }
    $payload = $stdin | ConvertFrom-Json
    $filePath = [string]$payload.tool_input.file_path
    if (-not $filePath) { exit 0 }

    # Always allow tracker/config/meta files (these ARE the tracking machinery).
    $norm = $filePath -replace '\\', '/'
    if ($norm -match '(^|/)\.project/' -or $norm -match '(^|/)\.claude/') { exit 0 }
    $base = Split-Path -Leaf $filePath
    if ($base -eq 'CLAUDE.md' -or $base -eq '.gitignore' -or $base -eq '.env' -or
        $base -like '.env.*' -or $base -eq 'package-lock.json' -or $base -like '*.lock') { exit 0 }

    $root = (git rev-parse --show-toplevel 2>$null | Select-Object -First 1)
    if (-not $root) { exit 0 }
    $root = $root.Trim()
    $issuesDir = Join-Path $root '.project/issues'
    if (-not (Test-Path $issuesDir)) { exit 0 }

    # Allow silently if any issue is in-progress.
    foreach ($dir in (Get-ChildItem -Path $issuesDir -Directory -ErrorAction SilentlyContinue)) {
        $ij = Join-Path $dir.FullName 'issue.json'
        if ((Test-Path $ij) -and ((Get-Content $ij -Raw -ErrorAction SilentlyContinue) -match '"in-progress"')) {
            exit 0
        }
    }

    # Throttle: only nag once per 2 minutes so we do not block every edit.
    $stateFile = Join-Path $root '.project/.hook-reminded'
    if (Test-Path $stateFile) {
        if (((Get-Date) - (Get-Item $stateFile).LastWriteTime).TotalMinutes -lt 2) { exit 0 }
    }
    New-Item -ItemType File -Path $stateFile -Force | Out-Null

    $msg = @"
[.project] No in-progress issue found in .project/issues/.
Before continuing, decide:
- Functional change (feature, bugfix, refactor) -> create an issue first (/create-issue), set it in-progress.
- Trivial edit (typo, formatting, config) -> proceed without an issue.
If an issue is needed: read .project/config.json for prefix + nextId, create
.project/issues/{PREFIX}-{ID}/ with issue.json + description.md, set status to
in-progress, and bump nextId. Otherwise explain why none is needed and proceed.
"@
    [Console]::Error.WriteLine($msg)
    exit 2
}
catch {
    exit 0
}
