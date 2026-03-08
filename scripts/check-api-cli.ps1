$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$php = Join-Path $repoRoot 'tools\php\php.exe'
if (-not (Test-Path $php)) {
    Write-Error 'Missing tools\php\php.exe'
}

$apiFile = Join-Path $repoRoot 'price_data\api.php'
$authCheckFile = Join-Path $repoRoot 'scripts\check-api-auth.php'
$historySyncCheckFile = Join-Path $repoRoot 'scripts\check-api-history-sync.php'
$dashboardGuardCheckFile = Join-Path $repoRoot 'scripts\check-api-dashboard-guard.php'

foreach ($file in @($apiFile, $authCheckFile, $historySyncCheckFile, $dashboardGuardCheckFile)) {
    & $php -l $file | Out-Host
    if ($LASTEXITCODE -ne 0) {
        Write-Error "PHP lint failed: $file"
    }
}

& $php $authCheckFile | Out-Host
if ($LASTEXITCODE -ne 0) {
    Write-Error 'API auth smoke check failed.'
}

& $php $historySyncCheckFile | Out-Host
if ($LASTEXITCODE -ne 0) {
    Write-Error 'History sync API smoke check failed.'
}

& $php $dashboardGuardCheckFile | Out-Host
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Dashboard guard API smoke check failed.'
}

Write-Host 'API CLI smoke checks passed.'
