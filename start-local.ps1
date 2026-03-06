$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = Join-Path $repoRoot 'tools\php\php.exe'
$appRoot = Join-Path $repoRoot 'price_data'
$configExample = Join-Path $appRoot 'config.example.php'
$configLocal = Join-Path $appRoot 'config.local.php'
$storageRoot = Join-Path $appRoot 'storage'
$cacheRoot = Join-Path $storageRoot 'cache'
$url = 'http://127.0.0.1:8080/login.php'
$skipBrowser = $env:PRICEJUST_SKIP_BROWSER -eq '1'

if (-not (Test-Path $php)) {
    Write-Host 'PHP runtime not found.' -ForegroundColor Red
    Write-Host 'Expected tools\php\php.exe inside the repository.' -ForegroundColor Yellow
    exit 1
}

if (-not (Test-Path $configExample)) {
    Write-Host 'Missing price_data\config.example.php.' -ForegroundColor Red
    exit 1
}

foreach ($dir in @($storageRoot, $cacheRoot)) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}

if (-not (Test-Path $configLocal)) {
    Copy-Item $configExample $configLocal
    Write-Host 'Created local config from price_data\config.example.php.' -ForegroundColor Yellow
}

& $php -v
if ($LASTEXITCODE -ne 0) {
    Write-Host 'PHP preflight failed.' -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host "Starting PriceJust local panel at $url" -ForegroundColor Green
Write-Host 'Login inicial: admin / change-me' -ForegroundColor Yellow
Write-Host 'Optional: configure api.key in price_data\config.local.php to enable Odds API live odds.' -ForegroundColor DarkYellow
Write-Host 'Press Ctrl+C to stop the server.' -ForegroundColor Yellow

Push-Location $appRoot
try {
    if (-not $skipBrowser) {
        try {
            Start-Process $url | Out-Null
        } catch {
            Write-Host 'Could not open the browser automatically. Open the URL manually if needed.' -ForegroundColor DarkYellow
        }
    }

    & $php -S 127.0.0.1:8080
} finally {
    Pop-Location
}
