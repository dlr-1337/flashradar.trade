# AGENTS.md

## Project Overview

This repository is a Windows-first local PHP app for tracking football odds and
manual entries. It ships with its own PHP runtime in `tools/php/`, serves the
app from `price_data/`, and does not use Composer, npm, or a build step.

The user-facing flow is:

1. Start the local server.
2. Sign in at `http://127.0.0.1:8080/login.php`.
3. View live Odds API rows when a local API key is configured.
4. Add, edit, and remove manual rows stored on disk.

## Important Paths

- `start-local.bat`: double-click entrypoint for Windows users.
- `start-local.ps1`: local server bootstrap and preflight checks.
- `price_data/login.php`: login page.
- `price_data/index.php`: authenticated dashboard UI and inline client script.
- `price_data/api.php`: JSON API for session, auth, listing, and manual CRUD.
- `price_data/lib/bootstrap.php`: shared config, auth, storage, API, and helper logic.
- `price_data/config.example.php`: committed example config.
- `price_data/config.local.php`: ignored local config with secrets and local overrides.
- `price_data/storage/manual.json`: ignored manual row storage.
- `price_data/storage/cache/dashboard.json`: ignored Odds API cache.
- `scripts/`: validation and smoke-check scripts.

## Local Commands

Use PowerShell from the repository root unless noted otherwise.

### Start The App

- `.\start-local.bat`
- `powershell -ExecutionPolicy Bypass -File .\start-local.ps1`

The app runs at `http://127.0.0.1:8080/login.php`.

To start without opening a browser:

```powershell
$env:PRICEJUST_SKIP_BROWSER='1'
powershell -ExecutionPolicy Bypass -File .\start-local.ps1
```

### Validation Commands

- PHP entrypoints and bootstrap checks:
  `powershell -ExecutionPolicy Bypass -File .\scripts\check-panel-php.ps1`
- API CLI smoke checks:
  `powershell -ExecutionPolicy Bypass -File .\scripts\check-api-cli.ps1`
- Frontend inline script parse check:
  `node .\scripts\check-front-syntax.mjs`
- Mojibake guard for key UI/data files.
  Requires `price_data/storage/manual.json` to exist:
  `powershell -ExecutionPolicy Bypass -File .\scripts\check-mojibake.ps1`
- Optional live Odds API integration check:
  `powershell -ExecutionPolicy Bypass -File .\scripts\check-live-api-integration.ps1`

### Maintenance Commands

Generate a new password hash for `price_data/config.local.php`:

```powershell
.\tools\php\php.exe -r "echo password_hash('NEW-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

Create an empty local manual store if a validation script expects it and it does
not exist yet:

```powershell
if (-not (Test-Path .\price_data\storage\manual.json)) { '[]' | Set-Content -Encoding utf8 .\price_data\storage\manual.json }
```

## Expected Workflows

### Backend Changes

If you change `price_data/api.php`, `price_data/lib/bootstrap.php`, config merge
logic, auth logic, storage helpers, or Odds API normalization:

1. Run `check-panel-php.ps1`.
2. Run `check-api-cli.ps1`.
3. Run `check-live-api-integration.ps1` if the change touches live Odds API behavior and a local API key is configured.

### Frontend Changes

If you change inline JS, dashboard markup, login flow UI, or frontend copy in
`price_data/index.php` or `price_data/login.php`:

1. Run `node .\scripts\check-front-syntax.mjs`.
2. Run `powershell -ExecutionPolicy Bypass -File .\scripts\check-mojibake.ps1` after `price_data/storage/manual.json` exists.
3. Do a manual browser smoke test with `start-local.ps1` when the change affects interaction or layout.

### Pre-Handoff Minimum

Before handing work back, run the checks that match the files you touched.
For mixed backend and frontend changes, run at least:

1. `powershell -ExecutionPolicy Bypass -File .\scripts\check-panel-php.ps1`
2. `powershell -ExecutionPolicy Bypass -File .\scripts\check-api-cli.ps1`
3. `node .\scripts\check-front-syntax.mjs`
4. `powershell -ExecutionPolicy Bypass -File .\scripts\check-mojibake.ps1` after `price_data/storage/manual.json` exists

## Editing Guidelines

- Prefer the bundled runtime at `tools/php/php.exe` instead of assuming PHP is installed globally.
- Keep shared behavior in `price_data/lib/bootstrap.php`; do not duplicate config, auth, path, or storage logic across pages.
- Preserve `declare(strict_types=1);` in PHP files.
- Follow the existing procedural PHP style in this repo. Do not introduce a framework or a large architectural rewrite for small changes.
- Keep the dashboard frontend dependency-free. The current UI uses inline CSS and inline JavaScript in PHP pages.
- Treat `price_data/index.php` as the primary dashboard entrypoint. `price_data/index.html` is not the active server-rendered page.
- Prefer ASCII-friendly copy for user-facing Portuguese text when practical, and run the mojibake check after editing text.

## Config And Secrets

- Never commit real API keys or other secrets.
- `price_data/config.local.php` is ignored and may contain live credentials. Do not overwrite a user's local values unless they explicitly ask.
- `price_data/storage/` contains runtime artifacts and local data. Do not commit generated cache or manual data files.
- If `config.local.php` does not exist, `start-local.ps1` will create it from `config.example.php`.

## Notes For Agents

- There is no package manager workflow to update here. Do not invent `npm`, `pnpm`, `composer`, or CI commands that do not exist in this repo.
- This project is optimized for local Windows use. Prefer PowerShell commands in documentation and change notes.
- When documenting commands, keep them copy-pasteable from the repository root.
