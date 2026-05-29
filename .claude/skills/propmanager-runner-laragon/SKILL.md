---
name: propmanager-runner-laragon
description: PropManager-specific runner + tooling commands for Windows + Laragon. Use instead of the laravelbootstrap-check / laravelrunner-selection / laraveldaily-workflow user-global skills, which assume Laravel Sail. This project runs on Laragon (Windows-native, no Docker, no Sail). Apply when running ANY shell command for this project.
---

# Runner + tooling on PropManager (Laragon, Windows)

This project does NOT use Laravel Sail or Docker. It runs on Laragon (Windows-native Apache/Nginx + MySQL + PHP). Commands the user-global Laravel skills suggest with `./vendor/bin/sail`, `./vendor/bin/pint`, `./vendor/bin/pest`, or `sail artisan` do not work or work incorrectly here.

If you're following `laravelbootstrap-check`, `laravelrunner-selection`, or `laraveldaily-workflow` from the user-global skill set, **stop and use this skill instead**.

## Where things live

| Tool | Path |
|---|---|
| PHP | `C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe` (on PATH as `php`) |
| Composer | `C:\laragon\bin\composer\composer.bat` (NOT on default Git Bash PATH — use full path) |
| Node + npm | `C:\Program Files\nodejs\` (on PATH) |
| MySQL | `C:\laragon\bin\mysql\mysql-8.4.x\` (managed via Laragon, see http://localhost/phpmyadmin) |
| Project root | `C:\laragon\www\PropManager\` |
| App URL | `http://propmanager.test` (NOT `localhost:8000`, NOT `localhost`) |
| Mailpit (E2E mail) | `http://localhost:8025` (UI + API) — see `memory/MEMORY.md` Mailpit section |

## The command map (canonical PropManager commands)

### Tests

```bash
php artisan test                     # full suite
php artisan test --parallel          # 8 workers
php artisan test --filter=X          # one filter
php artisan test path/to/Test.php    # one file or dir
```

NOT `./vendor/bin/pest` (doesn't exist on this project).
NOT `sail artisan test` (no Sail).

### Code style (Pint)

```bash
php vendor/bin/pint           # write mode (auto-format)
php vendor/bin/pint --test    # check mode (CI uses this)
php vendor/bin/pint path/to/File.php  # single file
```

**Note**: invoke as `php vendor/bin/pint`, NOT `./vendor/bin/pint`. The bare path works in Git Bash but `php vendor/bin/pint` is the version that survives PowerShell, cmd.exe, and CI consistently. Logged in `memory/MEMORY.md`.

### Static analysis (PHPMD)

```bash
./vendor/bin/phpmd app text phpmd.xml         # full audit (CI runs this)
./vendor/bin/phpmd app/Services/X.php text phpmd.xml  # single file
```

PHPMD threshold is `reportLevel="7"` (cyclomatic complexity >= 7 is flagged, not > 7).

### Composer

```bash
/c/laragon/bin/composer/composer.bat install
/c/laragon/bin/composer/composer.bat require <pkg>
/c/laragon/bin/composer/composer.bat audit --no-dev --locked --abandoned=fail   # CI's exact command
/c/laragon/bin/composer/composer.bat update "symfony/*" --with-dependencies      # targeted updates
```

Bare `composer` is not on Git Bash PATH. Use the full Laragon path.

### Artisan (the usual)

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan migrate:status
php artisan tinker
php artisan route:list --except-vendor
php artisan queue:listen --tries=1     # dev queue worker
php artisan pail                       # live log tail
```

### Frontend

```bash
npm install
npm run dev              # Vite dev server with HMR
npm run build            # production build
npm run lint             # ESLint
```

### Dev all-in-one

```bash
composer dev    # runs server + queue + pail + Vite concurrently
```

Or manually in separate terminals (per `tmux` or VS Code split):
```bash
php artisan serve
php artisan queue:listen --tries=1
php artisan pail --timeout=0
npm run dev
```

## Things that will silently fail on Windows

- `chmod 600 file` — runs but doesn't change Windows ACLs. AgentShield's "0o666" finding on `CLAUDE.md` is a Git Bash POSIX-mode-translation artefact, not a real exposure.
- `find . -name X -exec rm {} \;` — works in Git Bash, fails in PowerShell/cmd. Use the Grep / Glob tool instead.
- `2>/dev/null` in PowerShell — should be `2>$null`. Bash style works in Git Bash, not PowerShell.
- `irm | iex` PowerShell pattern — flagged by the safety classifier. Use `iwr -Uri ... -OutFile ...` then a separate execution step.
- Forward-slash paths in PowerShell — usually work but quote them: `"C:/path/with spaces/file.ext"`.

## The hooks are firing in every prompt

The `inject-context.ps1` hook fires on every UserPromptSubmit and outputs `[session-context branch=... ticket= last_commit='...']` at the top of every user prompt. If you don't see that line, the hooks aren't running and `.claude/settings.json` may not be loaded.

The `auto-format.ps1` hook fires after every Edit/Write of a `.php`, `.vue`, `.js`, `.ts` file and runs the project's Pint or Prettier silently. You should not need to invoke Pint after editing — the hook already did.

`block-dangerous-commands.ps1` will refuse `rm -rf /`, `git push --force` (without `--force-with-lease`), `DROP DATABASE`, etc. If a command gets blocked unexpectedly, that's the hook firing — read the stderr message.

## Health check before starting any session

```bash
php --version                                              # expect PHP 8.4.x
/c/laragon/bin/composer/composer.bat --version             # expect Composer 2.x
node --version                                             # expect Node 20+ for Vite 7
php artisan migrate:status | tail -5                       # latest migration ran
curl -s -o /dev/null -w '%{http_code}\n' http://propmanager.test   # expect 200 or 302 (NOT connection refused)
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8025/api/v1/messages  # expect 200 (Mailpit alive)
```

If `propmanager.test` returns connection refused: Laragon isn't running. Start it from the system tray.

## E2E (Dusk + agent-browser)

- Always use `--session e2e` flag with agent-browser: `agent-browser --session e2e snapshot -i`. Bare `agent-browser ...` is unreliable.
- Dusk on first run takes ~80s to start ChromeDriver. Subsequent tests ~1.5s.
- See `memory/MEMORY.md` E2E sections for the full setup.
