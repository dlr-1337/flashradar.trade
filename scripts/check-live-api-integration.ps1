$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$php = Join-Path $repoRoot 'tools\php\php.exe'
if (-not (Test-Path $php)) {
    Write-Error 'Missing tools\php\php.exe'
}

$configJson = & $php -r "require 'price_data/lib/bootstrap.php'; echo json_encode(['key' => (string) (pj_config()['api']['key'] ?? ''), 'configured' => pj_has_api_key()], JSON_UNESCAPED_SLASHES);"
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Could not read the merged application config.'
}

$config = $configJson | ConvertFrom-Json
if ($config.configured -ne $true) {
    Write-Error 'API key is not configured. Update price_data/config.local.php and rerun this optional live integration check.'
}
$key = [string]$config.key

$headers = @{
    'x-apisports-key' = $key
    'Accept' = 'application/json'
    'User-Agent' = 'PriceJustLocal/1.0'
}

$date = Get-Date -Format 'yyyy-MM-dd'
$timezone = 'America/Sao_Paulo'

$todayFixtures = Invoke-RestMethod -Headers $headers -Uri "https://v3.football.api-sports.io/fixtures?date=$date&timezone=$timezone" -Method Get
$todayOdds = Invoke-RestMethod -Headers $headers -Uri "https://v3.football.api-sports.io/odds?date=$date" -Method Get
$liveFixtures = Invoke-RestMethod -Headers $headers -Uri "https://v3.football.api-sports.io/fixtures?live=all&timezone=$timezone" -Method Get
$liveOdds = Invoke-RestMethod -Headers $headers -Uri 'https://v3.football.api-sports.io/odds/live' -Method Get

$fixtureIds = [System.Collections.Generic.HashSet[int]]::new()
foreach ($entry in @($todayOdds.response)) {
    $fixtureId = [int]($entry.fixture.id)
    if ($fixtureId -gt 0) {
        [void]$fixtureIds.Add($fixtureId)
    }
}
foreach ($entry in @($liveOdds.response)) {
    $fixtureId = [int]($entry.fixture.id)
    if ($fixtureId -gt 0) {
        [void]$fixtureIds.Add($fixtureId)
    }
}

$liveFixtureIds = [System.Collections.Generic.HashSet[int]]::new()
foreach ($entry in @($liveOdds.response)) {
    $fixtureId = [int]($entry.fixture.id)
    if ($fixtureId -gt 0) {
        [void]$liveFixtureIds.Add($fixtureId)
    }
}

$probePhp = @'
<?php
require 'price_data/lib/bootstrap.php';

$result = pj_fetch_api_rows();
echo json_encode([
    'rowCount' => count($result['rows'] ?? []),
    'rows' => ($result['rows'] ?? []),
    'meta' => ($result['meta'] ?? []),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
'@
$probeFile = Join-Path $env:TEMP 'pricejust-live-api-check.php'
Set-Content -Path $probeFile -Value $probePhp -Encoding ascii
$localJson = & $php $probeFile
Remove-Item $probeFile -Force -ErrorAction SilentlyContinue

if ($LASTEXITCODE -ne 0) {
    Write-Error 'Local pj_fetch_api_rows execution failed.'
}

$local = $localJson | ConvertFrom-Json
$localFixtureIds = [System.Collections.Generic.HashSet[int]]::new()
foreach ($row in @($local.rows)) {
    $fixtureId = [int]($row.fixture_id)
    if ($fixtureId -gt 0) {
        [void]$localFixtureIds.Add($fixtureId)
    }
}

$expectedFixtureCount = $fixtureIds.Count
$matchedLive = 0
foreach ($fixtureId in $liveFixtureIds) {
    if ($localFixtureIds.Contains($fixtureId)) {
        $matchedLive++
    }
}

if ($expectedFixtureCount -gt 0 -and [int]$local.rowCount -le 0) {
    Write-Error "Expected normalized API rows because upstream returned odds for $expectedFixtureCount fixtures, but pj_fetch_api_rows returned zero rows. Meta: $($local.meta | ConvertTo-Json -Compress)"
}

if ($liveFixtureIds.Count -gt 0 -and $matchedLive -le 0) {
    Write-Error "Expected at least one live fixture to be normalized because upstream live odds returned $($liveFixtureIds.Count) fixtures, but none appeared in pj_fetch_api_rows."
}

if ($local.meta.stale -eq $true) {
    Write-Error "pj_fetch_api_rows returned stale=true during live integration check. Meta: $($local.meta | ConvertTo-Json -Compress)"
}

Write-Host "Live API integration check passed. Normalized rows: $($local.rowCount). Live fixtures matched: $matchedLive."
