# UserPromptSubmit hook — inject current branch + ticket info as additional context.
# Output to stdout is appended to the prompt context.

$ErrorActionPreference = 'SilentlyContinue'

try {
    $branch = git rev-parse --abbrev-ref HEAD 2>$null
    if (-not $branch) { $branch = 'unknown' }

    # Try to extract a ticket reference from the branch name
    $ticket = ''
    if ($branch -match '(?i)(PROP|FEAT|BUG|FIX|CHORE|REFACTOR|DBP|PAY|NOTIF)-?\d+') {
        $ticket = $matches[0]
    }

    $lastCommit = git log -1 --pretty=format:'%h %s' 2>$null
    if (-not $lastCommit) { $lastCommit = '(no commits yet)' }

    Write-Output "[session-context branch=$branch ticket=$ticket last_commit='$lastCommit']"
    exit 0
} catch {
    exit 0
}
