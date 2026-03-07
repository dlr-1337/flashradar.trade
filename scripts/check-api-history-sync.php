<?php

declare(strict_types=1);

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . ' Expected: ' . var_export($expected, true)
            . ' Actual: ' . var_export($actual, true)
        );
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function run_php_json(string $code): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-r', $code], $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start PHP subprocess.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $raw = trim((string) $stdout);
    $errorText = trim((string) $stderr);

    if ($exitCode !== 0) {
        throw new RuntimeException('PHP subprocess failed: ' . ($errorText !== '' ? $errorText : $raw));
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $suffix = $errorText !== '' ? ' STDERR: ' . $errorText : '';
        throw new RuntimeException('Invalid JSON returned by API subprocess: ' . $raw . $suffix);
    }

    return $decoded;
}

$repoRoot = dirname(__DIR__);
$apiFile = str_replace('\\', '/', $repoRoot . '/price_data/api.php');
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flashradar-api-history-sync-' . bin2hex(random_bytes(6));

if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
    throw new RuntimeException('Unable to create temp directory for API history sync checks.');
}

$jsonFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'dados.json');
$stateFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'history-sync.json');
$manualFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'manual.json');
$cacheFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'dashboard.json');

file_put_contents($jsonFile, json_encode([
    [
        'category' => 'Legacy',
        'date' => '2025-12',
        'id' => 'legacy-row',
        'league' => 'Legacy League',
        'odd_ft' => '3.20',
        'odd_ht' => '2.10',
        'score_ht' => '0x0',
        'team' => 'Legacy FC',
        'venue' => 'Casa',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$unauthCode = strtr(<<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'sync_history';
include __API_FILE__;
PHP, [
    '__API_FILE__' => var_export($apiFile, true),
]);

$unauthPayload = run_php_json($unauthCode);
assert_same(false, $unauthPayload['ok'] ?? null, 'sync_history should reject unauthenticated requests.');
assert_same('AUTH_REQUIRED', $unauthPayload['error'] ?? null, 'sync_history should expose AUTH_REQUIRED for unauthenticated requests.');

$authCode = strtr(<<<'PHP'
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['pricejust_user'] = 'admin';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'sync_history';
$GLOBALS['__PJ_REQUEST_BODY'] = ['reset' => true];
$GLOBALS['__PJ_FILE_OVERRIDES'] = [
    'manual' => __MANUAL_FILE__,
    'cache' => __CACHE_FILE__,
    'json_source' => __JSON_FILE__,
    'history_sync_state' => __STATE_FILE__,
];
$GLOBALS['__PJ_CONFIG_OVERRIDE'] = [
    'api' => [
        'key' => 'test-key',
        'base_url' => 'https://mock.odds-api.local/v3',
        'sport' => 'football',
        'timezone' => 'America/Sao_Paulo',
        'bookmakers' => ['Bet365'],
    ],
];
$GLOBALS['__PJ_HISTORY_SYNC_NOW'] = '2025-12-31T23:59:59-03:00';
$GLOBALS['__PJ_HTTP_JSON_OVERRIDE'] = static function (string $method, string $url, array $headers): array {
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';
    parse_str($parsed['query'] ?? '', $query);

    if ($path === '/v3/leagues') {
        return [
            [
                'name' => 'League A',
                'slug' => 'league-a',
                'sport' => ['slug' => 'football'],
            ],
        ];
    }

    if ($path === '/v3/historical/events') {
        return [
            [
                'id' => 111,
                'home' => 'Home FC',
                'away' => 'Away FC',
                'date' => '2025-12-14T19:00:00Z',
                'status' => 'finished',
                'league' => [
                    'name' => 'League A',
                    'slug' => 'league-a',
                ],
                'scores' => [
                    'home' => 2,
                    'away' => 1,
                ],
            ],
            [
                'id' => 222,
                'home' => 'Skip FC',
                'away' => 'No Odds FC',
                'date' => '2025-12-15T19:00:00Z',
                'status' => 'finished',
                'league' => [
                    'name' => 'League A',
                    'slug' => 'league-a',
                ],
                'scores' => [
                    'home' => 0,
                    'away' => 0,
                ],
            ],
        ];
    }

    if ($path === '/v3/historical/odds') {
        $eventId = (int) ($query['eventId'] ?? 0);
        if ($eventId === 111) {
            return [
                'id' => 111,
                'home' => 'Home FC',
                'away' => 'Away FC',
                'date' => '2025-12-14T19:00:00Z',
                'status' => 'finished',
                'bookmakers' => [
                    'Bet365' => [
                        [
                            'name' => 'ML',
                            'odds' => [
                                [
                                    'home' => '2.40',
                                    'draw' => '3.20',
                                    'away' => '2.80',
                                ],
                            ],
                        ],
                        [
                            'name' => 'Half Time Result',
                            'odds' => [
                                [
                                    'label' => '1',
                                    'under' => '1.70',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if ($eventId === 222) {
            return [
                'id' => 222,
                'home' => 'Skip FC',
                'away' => 'No Odds FC',
                'date' => '2025-12-15T19:00:00Z',
                'status' => 'finished',
                'bookmakers' => [],
            ];
        }
    }

    throw new RuntimeException('Unexpected mocked request: ' . $path);
};
include __API_FILE__;
PHP, [
    '__API_FILE__' => var_export($apiFile, true),
    '__JSON_FILE__' => var_export($jsonFile, true),
    '__STATE_FILE__' => var_export($stateFile, true),
    '__MANUAL_FILE__' => var_export($manualFile, true),
    '__CACHE_FILE__' => var_export($cacheFile, true),
]);

$payload = run_php_json($authCode);
assert_same(true, $payload['ok'] ?? false, 'Authenticated sync_history call should return ok=true.');
assert_same('completed', $payload['state'] ?? null, 'Authenticated sync_history call should complete with the mocked payload.');
assert_same(2, (int) ($payload['counts']['inserted'] ?? -1), 'sync_history should insert both managed rows from the mocked event.');
assert_same(0, (int) ($payload['counts']['updated'] ?? -1), 'sync_history should not report updates when no managed rows existed yet.');
assert_same(1, (int) ($payload['counts']['skipped'] ?? -1), 'sync_history should report skipped events without valid odds.');
assert_same(1, (int) ($payload['counts']['preserved'] ?? -1), 'sync_history should preserve legacy JSON rows.');
assert_true(is_array($payload['progress'] ?? null), 'sync_history should expose structured progress data.');
assert_same(1, (int) ($payload['progress']['total'] ?? -1), 'sync_history progress should count the mocked league/window task.');
assert_same(100, (int) ($payload['progress']['percent'] ?? -1), 'sync_history progress should reach 100 percent when completed.');

$storedRows = json_decode((string) file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR);
assert_same(3, count($storedRows), 'dados.json should contain the preserved row plus the managed API rows.');

$storedById = [];
foreach ($storedRows as $row) {
    $storedById[(string) ($row['id'] ?? '')] = $row;
}

assert_true(isset($storedById['legacy-row']), 'Legacy rows should remain untouched after sync_history.');
assert_true(isset($storedById['oddsapi_111_home']), 'sync_history should persist the deterministic home id.');
assert_true(isset($storedById['oddsapi_111_away']), 'sync_history should persist the deterministic away id.');
assert_same('2025-12', $storedById['oddsapi_111_home']['date'] ?? null, 'Historical sync rows should persist the month label only.');
assert_same('1.7', $storedById['oddsapi_111_home']['odd_ht'] ?? null, 'Historical sync rows should persist HT odds when available.');
assert_same('', $storedById['oddsapi_111_away']['odd_ht'] ?? null, 'Historical sync rows should keep empty HT odds when the market side is unavailable.');

$statePayload = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
assert_same('completed', $statePayload['status'] ?? null, 'History sync state cache should persist the completed status.');

echo "API history sync checks passed.\n";
