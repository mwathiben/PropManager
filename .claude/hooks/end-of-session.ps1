# Stop hook — write a session journal entry to docs/journal/ on session end.
# Non-blocking; failure does not interrupt session close.

$ErrorActionPreference = 'SilentlyContinue'

try {
    $dateStr = Get-Date -Format 'yyyy-MM-dd'
    $journalDir = 'docs/journal'
    $journalFile = "$journalDir/$dateStr.md"

    if (-not (Test-Path $journalDir)) {
        New-Item -ItemType Directory -Path $journalDir -Force | Out-Null
    }

    # Append timestamped entry; don't overwrite existing same-day entries
    $timestamp = Get-Date -Format 'HH:mm'
    $commits = (git log --since=midnight --oneline 2>$null) -join "`n  "
    if (-not $commits) { $commits = '(no commits this session)' }

    $status = (git status --short 2>$null) -join "`n  "
    if (-not $status) { $status = '(clean)' }

    $entry = @"

---
## Session ended $timestamp

**Commits today:**
  $commits

**Working tree at end:**
  $status

"@

    Add-Content -Path $journalFile -Value $entry -Encoding utf8
    exit 0
} catch {
    exit 0
}
