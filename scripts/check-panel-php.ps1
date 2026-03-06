$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$phpLocal = Join-Path $repoRoot 'tools\php\php.exe'
$phpPath = (Get-Command php -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Source)
$php = if (Test-Path $phpLocal) { $phpLocal } elseif ($phpPath) { $phpPath } else { $null }

if (-not $php) {
    Write-Error 'PHP runtime not found. Expected tools\php\php.exe or php in PATH.'
}

$required = @(
    (Join-Path $repoRoot 'price_data\index.php'),
    (Join-Path $repoRoot 'price_data\login.php'),
    (Join-Path $repoRoot 'price_data\api.php'),
    (Join-Path $repoRoot 'scripts\check-bootstrap-upstream-errors.php'),
    (Join-Path $repoRoot 'scripts\check-bootstrap-odds-api.php')
)

foreach ($file in $required) {
    if (-not (Test-Path $file)) {
        Write-Error "Missing required PHP file: $file"
    }
}

foreach ($file in $required) {
    & $php -l $file | Out-Host
    if ($LASTEXITCODE -ne 0) {
        Write-Error "PHP lint failed: $file"
    }
}

& $php (Join-Path $repoRoot 'scripts\check-bootstrap-upstream-errors.php') | Out-Host
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Bootstrap upstream error checks failed.'
}

& $php (Join-Path $repoRoot 'scripts\check-bootstrap-odds-api.php') | Out-Host
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Bootstrap Odds API checks failed.'
}

Write-Host 'PHP entrypoints linted successfully.'
