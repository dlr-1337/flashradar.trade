$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$paths = @(
    (Join-Path $repoRoot 'price_data\index.php'),
    (Join-Path $repoRoot 'price_data\login.php'),
    (Join-Path $repoRoot 'price_data\admin.php'),
    (Join-Path $repoRoot 'price_data\change-password.php'),
    (Join-Path $repoRoot 'price_data\storage\manual.json')
)
$markers = @(
    [string][char]0x00C3,
    [string][char]0x00C2,
    ([string][char]0x00E2 + [char]0x20AC + [char]0x201D),
    ([string][char]0x00E2 + [char]0x20AC + [char]0x00A2),
    ([string][char]0x00E2 + [char]0x2013),
    ([string][char]0x00E2 + [char]0x2020),
    ([string][char]0x00E2 + [char]0x02C6),
    ([string][char]0x00E2 + [char]0x0153),
    ([string][char]0x00F0 + [char]0x0178),
    [string][char]0xFFFD
)
$pattern = ($markers | ForEach-Object { [regex]::Escape($_) }) -join '|'

$findings = @()
foreach ($path in $paths) {
    if (-not (Test-Path $path)) {
        Write-Error "Missing file: $path"
    }

    $content = [System.IO.File]::ReadAllText($path, [System.Text.Encoding]::UTF8)
    $lines = $content -split "`r?`n"
    for ($i = 0; $i -lt $lines.Length; $i++) {
        if ($lines[$i] -cmatch $pattern) {
            $findings += [pscustomobject]@{
                Path = $path
                LineNumber = $i + 1
                Line = $lines[$i].Trim()
            }
        }
    }
}

if ($findings.Count -gt 0) {
    Write-Host "Mojibake check failed. Occurrences: $($findings.Count)"
    $findings | Select-Object -First 12 | Format-Table -AutoSize
    exit 1
}

Write-Host 'Mojibake check passed.'