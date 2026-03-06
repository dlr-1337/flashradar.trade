$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$php = Join-Path $repoRoot 'tools\php\php.exe'
if (-not (Test-Path $php)) {
    Write-Error 'Missing tools\php\php.exe'
}

$configJson = & $php -r "require 'price_data/lib/bootstrap.php'; echo json_encode(['key' => (string) (pj_config()['api']['key'] ?? ''), 'configured' => pj_has_api_key(), 'base_url' => (string) (pj_config()['api']['base_url'] ?? ''), 'sport' => (string) (pj_config()['api']['sport'] ?? 'football'), 'timezone' => (string) (pj_config()['api']['timezone'] ?? 'America/Sao_Paulo'), 'bookmakers' => array_values((array) (pj_config()['api']['bookmakers'] ?? []))], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);"
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Could not read the merged application config.'
}

$config = $configJson | ConvertFrom-Json
if ($config.configured -ne $true) {
    Write-Error 'API key is not configured. Update price_data/config.local.php and rerun this optional live integration check.'
}

$baseUrl = [string]$config.base_url
$sport = [string]$config.sport
$bookmakers = @($config.bookmakers | ForEach-Object { [string]$_ } | Where-Object { $_ })
if ($bookmakers.Count -le 0) {
    Write-Error 'No bookmakers configured. Update api.bookmakers in price_data/config.local.php.'
}

$now = Get-Date
$endOfDay = $now.Date.AddDays(1).AddSeconds(-1)
$from = $now.ToString('yyyy-MM-ddTHH:mm:ssK')
$to = $endOfDay.ToString('yyyy-MM-ddTHH:mm:ssK')
$bookmakersCsv = [string]::Join(',', $bookmakers)

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
if ($local.meta.stale -eq $true) {
    Write-Error "pj_fetch_api_rows returned stale=true during live integration check. Meta: $($local.meta | ConvertTo-Json -Compress)"
}

$liveEvents = Invoke-RestMethod -Uri "$baseUrl/events/live?apiKey=$($config.key)&sport=$sport" -Method Get
$todayEvents = Invoke-RestMethod -Uri "$baseUrl/events?apiKey=$($config.key)&sport=$sport&from=$([uri]::EscapeDataString($from))&to=$([uri]::EscapeDataString($to))&status=pending,live&limit=250" -Method Get

$eventsById = @{}
foreach ($entry in @($todayEvents) + @($liveEvents)) {
    if ($null -eq $entry) {
        continue
    }

    $eventId = [int]$entry.id
    if ($eventId -gt 0) {
        $eventsById[$eventId] = $entry
    }
}

$eventIds = @($eventsById.Keys | Sort-Object | Select-Object -First 10)
$upstreamOdds = @()
if ($eventIds.Count -gt 0) {
    $idsCsv = [string]::Join(',', @($eventIds))
    $payload = Invoke-RestMethod -Uri "$baseUrl/odds/multi?apiKey=$($config.key)&eventIds=$idsCsv&bookmakers=$([uri]::EscapeDataString($bookmakersCsv))" -Method Get
    $upstreamOdds += @($payload)
}

$upstreamOddsIds = [System.Collections.Generic.HashSet[int]]::new()
foreach ($entry in @($upstreamOdds)) {
    $eventId = [int]$entry.id
    if ($eventId -gt 0) {
        [void]$upstreamOddsIds.Add($eventId)
    }
}

$localFixtureIds = [System.Collections.Generic.HashSet[int]]::new()
$rowsWithFt = 0
foreach ($row in @($local.rows)) {
    $fixtureId = [int]$row.fixture_id
    if ($fixtureId -gt 0) {
        [void]$localFixtureIds.Add($fixtureId)
    }

    if (-not [string]::IsNullOrWhiteSpace([string]$row.odd_ft) -and -not [string]::IsNullOrWhiteSpace([string]$row.bookmaker)) {
        $rowsWithFt++
    }
}

$matchedOdds = 0
foreach ($eventId in $upstreamOddsIds) {
    if ($localFixtureIds.Contains($eventId)) {
        $matchedOdds++
    }
}

if ($upstreamOddsIds.Count -gt 0 -and [int]$local.rowCount -le 0) {
    Write-Error "Expected normalized Odds API rows because upstream returned odds for $($upstreamOddsIds.Count) events, but pj_fetch_api_rows returned zero rows. Meta: $($local.meta | ConvertTo-Json -Compress)"
}

if ($upstreamOddsIds.Count -gt 0 -and $matchedOdds -le 0) {
    Write-Error "Expected at least one upstream odds event to be normalized locally, but none appeared in pj_fetch_api_rows."
}

if ($upstreamOddsIds.Count -gt 0 -and $rowsWithFt -le 0) {
    Write-Error 'Expected local rows with odd_ft and bookmaker filled from Odds API.'
}

Write-Host "Live Odds API integration check passed. Events: $($eventIds.Count). Odds payloads: $($upstreamOddsIds.Count). Normalized rows: $($local.rowCount)."
