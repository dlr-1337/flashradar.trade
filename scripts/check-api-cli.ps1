$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$php = Join-Path $repoRoot 'tools\php\php.exe'
if (-not (Test-Path $php)) {
    Write-Error 'Missing tools\php\php.exe'
}

$apiFile = Join-Path $repoRoot 'price_data\api.php'
$historySyncCheckFile = Join-Path $repoRoot 'scripts\check-api-history-sync.php'

$apiFilePhp = $apiFile.Replace('\\', '/')
$sessionCode = @(
    '$_SERVER[''REQUEST_METHOD''] = ''GET'';',
    '$_GET[''action''] = ''session'';',
    ('include ''{0}'';' -f $apiFilePhp)
) -join "`n"
$sessionResult = & $php -r $sessionCode

if ($LASTEXITCODE -ne 0) {
    Write-Error 'Session endpoint execution failed.'
}

$loginCode = @(
    '$_SERVER[''REQUEST_METHOD''] = ''POST'';',
    '$_GET[''action''] = ''login'';',
    '$GLOBALS[''__PJ_REQUEST_BODY''] = [''username'' => ''admin'', ''password'' => ''change-me''];',
    ('include ''{0}'';' -f $apiFilePhp)
) -join "`n"
$loginResult = & $php -r $loginCode

if ($LASTEXITCODE -ne 0) {
    Write-Error 'Login endpoint execution failed.'
}

$listCode = @(
    '$_SERVER[''REQUEST_METHOD''] = ''GET'';',
    ('include ''{0}'';' -f $apiFilePhp)
) -join "`n"
$listResult = & $php -r $listCode

if ($LASTEXITCODE -ne 0) {
    Write-Error 'List endpoint execution failed.'
}

$sessionJson = $sessionResult | ConvertFrom-Json
$loginJson = $loginResult | ConvertFrom-Json
$listJson = $listResult | ConvertFrom-Json

if ($sessionJson.ok -ne $true) {
    Write-Error 'Session endpoint did not return ok=true.'
}

if ($loginJson.ok -ne $true) {
    Write-Error 'Login endpoint did not return ok=true.'
}

if ($listJson.ok -ne $true) {
    Write-Error 'List endpoint did not return ok=true.'
}

if ($null -eq $listJson.rows) {
    Write-Error 'List endpoint did not return rows.'
}

$dadosFile = Join-Path $repoRoot 'dados.json'
if (Test-Path $dadosFile) {
    $jsonRows = @($listJson.rows | Where-Object { $_.source -eq 'json' })
    if ($jsonRows.Count -eq 0) {
        Write-Error 'List endpoint did not expose rows from dados.json.'
    }
}

$historyLint = & $php -l $historySyncCheckFile
if ($LASTEXITCODE -ne 0) {
    Write-Error 'API history sync check script failed PHP lint.'
}

$historyResult = & $php $historySyncCheckFile
if ($LASTEXITCODE -ne 0) {
    Write-Error 'History sync API smoke check failed.'
}

Write-Host 'API CLI smoke checks passed.'