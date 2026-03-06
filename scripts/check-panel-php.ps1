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
    (Join-Path $repoRoot 'price_data\api.php')
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

Write-Host 'PHP entrypoints linted successfully.'
