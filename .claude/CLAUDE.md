# Security Guidelines

## Secrets

- NEVER hardcode API keys, tokens, passwords, or credentials in any file
- Always use environment variable references: `${VAR_NAME}` or `process.env.VAR_NAME`
- Never echo, log, or print secret values to the terminal

## Permissions

- Never use `--dangerously-skip-permissions` or `--no-verify`
- Do not run `sudo` commands
- Do not use `rm -rf` without explicit user confirmation
- Do not use `chmod 777` on any file or directory

## Code Safety

- Validate all user inputs before processing
- Use parameterized queries for database operations
- Sanitize HTML output to prevent XSS
- Never execute dynamically constructed shell commands with user input

## MCP Servers

- Only connect to trusted, verified MCP servers
- Review MCP server permissions before enabling
- Do not pass secrets as command-line arguments to MCP servers
- Use environment variables for MCP server credentials

## Hooks

- All hooks must be reviewed before activation
- Hooks should not exfiltrate data or make external network calls
- PostToolUse hooks should validate output, not modify it silently

  **Permitted exception — deterministic, value-preserving formatters.**
  PostToolUse hooks MAY run `pint`, `prettier`, `gofmt`, `rustfmt`, or
  similar formatters on the just-edited file. The exception applies only
  when ALL of the following hold:

  1. The tool is purely syntactic (whitespace, quoting, ordering) and
     never changes program behaviour or semantics.
  2. The tool runs offline (no network, no telemetry).
  3. The tool is the project's pinned formatter (in `composer.json`,
     `package.json`, or equivalent) — not an ad-hoc choice by the hook.
  4. The hook only touches the specific file the upstream tool just
     edited (no broad sweeps, no recursive rewrites).

  Our `.claude/hooks/auto-format.ps1` qualifies: it pins `vendor/bin/pint`
  for `.php` and `node_modules/.bin/prettier` for `.vue|.js|.ts`, runs
  each silently on a single file path, and has no network surface.
