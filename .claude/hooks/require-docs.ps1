# PreToolUse hook (Edit|Write): when an issue.json is being set to status
# "done", require that the work was documented in the wiki (or labelled
# skip-docs/trivial/chore). Windows/PowerShell port of require-docs.sh.
#
# Exit 0 = allow, Exit 2 = block. FAIL-OPEN on any error.

try {
    $stdin = [Console]::In.ReadToEnd()
    if (-not $stdin) { exit 0 }
    $payload = $stdin | ConvertFrom-Json
    $filePath = [string]$payload.tool_input.file_path
    if (-not $filePath) { exit 0 }

    $norm = $filePath -replace '\\', '/'
    if ($norm -notmatch '\.project/issues/[^/]+/issue\.json$') { exit 0 }

    # Only act when this edit is flipping the issue to "done".
    if ($stdin -notmatch 'status' -or $stdin -notmatch 'done') { exit 0 }

    $root = (git rev-parse --show-toplevel 2>$null | Select-Object -First 1)
    if (-not $root) { exit 0 }
    $root = $root.Trim()

    $issueId = ($norm -replace '.*/issues/([^/]+)/issue\.json$', '$1')
    $issueDir = Join-Path $root ".project/issues/$issueId"
    if (-not (Test-Path $issueDir)) { exit 0 }

    $issueJson = Join-Path $issueDir 'issue.json'
    $issueContent = if (Test-Path $issueJson) { Get-Content $issueJson -Raw -ErrorAction SilentlyContinue } else { '' }
    if ($issueContent -match '"skip-docs"|"trivial"|"chore"') { exit 0 }

    $documented = $false
    $commentsDir = Join-Path $issueDir 'comments'
    if (Test-Path $commentsDir) {
        foreach ($c in (Get-ChildItem -Path $commentsDir -Filter *.json -ErrorAction SilentlyContinue)) {
            if ((Get-Content $c.FullName -Raw -ErrorAction SilentlyContinue) -match 'documented in wiki|wiki.*updated|/document-completion|[Ff]unctional:|[Tt]echnical:|[Dd]ecision') {
                $documented = $true; break
            }
        }
    }
    if (-not $documented) {
        $wikiDir = Join-Path $root '.project/wiki/pages'
        if (Test-Path $wikiDir) {
            foreach ($w in (Get-ChildItem -Path $wikiDir -Filter *.md -ErrorAction SilentlyContinue)) {
                if ((Get-Content $w.FullName -Raw -ErrorAction SilentlyContinue) -match [regex]::Escape($issueId)) {
                    $documented = $true; break
                }
            }
        }
    }

    if (-not $documented) {
        [Console]::Error.WriteLine(@"
[.project] Marking $issueId as done, but no wiki documentation found.
Decide if documentation is needed:
- User-facing change   -> run /document-completion (functional doc)
- Architecture change  -> run /document-completion (technical doc)
- Trivial fix          -> add "skip-docs" to the issue's labels array, then retry.
"@)
        exit 2
    }
    exit 0
}
catch {
    exit 0
}
