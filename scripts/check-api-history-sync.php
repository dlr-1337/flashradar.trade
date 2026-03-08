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
$usersFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'users.json');

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
    [
        'category' => 'Managed',
        'date' => '2025-12',
        'id' => 'oddsapi_111_home',
        'league' => 'League A',
        'odd_ft' => '2.1',
        'odd_ht' => '1.5',
        'score_ht' => '0x0',
        'team' => 'Old Home',
        'venue' => 'Casa',
        'fixture_id' => 111,
        'kickoff_at' => '2025-12-14T19:00:00Z',
        'match_status' => 'FT',
        'bookmaker' => 'Legacy',
        'opponent' => 'Old Away',
    ],
    [
        'category' => 'Exact',
        'date' => '2025-12',
        'id' => 'outside-exact-range',
        'league' => 'League A',
        'odd_ft' => '4.5',
        'odd_ht' => '3.2',
        'score_ht' => '1x1',
        'team' => 'Outside Exact FC',
        'venue' => 'Casa',
        'kickoff_at' => '2025-12-29T19:00:00Z',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

file_put_contents($usersFile, json_encode([
    [
        'id' => 'admin_general',
        'username' => 'admin',
        'password_hash' => 'seeded-admin-hash',
        'role' => 'admin',
        'active' => true,
        'created_at' => '2026-01-01T00:00:00+00:00',
        'updated_at' => '2026-01-01T00:00:00+00:00',
    ],
    [
        'id' => 'user_operator',
        'username' => 'operador',
        'password_hash' => 'seeded-user-hash',
        'role' => 'user',
        'active' => true,
        'created_at' => '2026-01-01T00:00:00+00:00',
        'updated_at' => '2026-01-01T00:00:00+00:00',
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

$blockedUserCode = strtr(<<<'PHP'
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['pricejust_user'] = 'operador';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'sync_history';
$GLOBALS['__PJ_FILE_OVERRIDES'] = [
    'users' => __USERS_FILE__,
    'manual' => __MANUAL_FILE__,
    'cache' => __CACHE_FILE__,
    'json_source' => __JSON_FILE__,
    'history_sync_state' => __STATE_FILE__,
];
include __API_FILE__;
PHP, [
    '__API_FILE__' => var_export($apiFile, true),
    '__JSON_FILE__' => var_export($jsonFile, true),
    '__STATE_FILE__' => var_export($stateFile, true),
    '__USERS_FILE__' => var_export($usersFile, true),
    '__MANUAL_FILE__' => var_export($manualFile, true),
    '__CACHE_FILE__' => var_export($cacheFile, true),
]);

$blockedUserPayload = run_php_json($blockedUserCode);
assert_same(false, $blockedUserPayload['ok'] ?? null, 'sync_history should reject normal users.');
assert_same('ADMIN_REQUIRED', $blockedUserPayload['error'] ?? null, 'sync_history should expose ADMIN_REQUIRED for normal users.');

$historyPreviewCode = strtr(<<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['mode'] = 'history';
$_GET['date_from'] = '2025-12-10';
$_GET['date_to'] = '2025-12-20';
$GLOBALS['__PJ_FILE_OVERRIDES'] = [
    'users' => __USERS_FILE__,
    'manual' => __MANUAL_FILE__,
    'cache' => __CACHE_FILE__,
    'json_source' => __JSON_FILE__,
    'history_sync_state' => __STATE_FILE__,
];
$GLOBALS['__PJ_CONFIG_OVERRIDE'] = [
    'auth' => [
        'username' => 'admin',
    ],
    'api' => [
        'key' => 'test-key',
        'base_url' => 'https://mock.odds-api.local/v3',
        'sport' => 'football',
        'timezone' => 'America/Sao_Paulo',
        'bookmakers' => ['Bet365'],
    ],
];
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
                'id' => 333,
                'home' => 'Pending FC',
                'away' => 'Waiting FC',
                'date' => '2025-12-16T19:00:00Z',
                'status' => 'pending',
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
    }

    throw new RuntimeException('Unexpected mocked request: ' . $path);
};
include __API_FILE__;
PHP, [
    '__API_FILE__' => var_export($apiFile, true),
    '__JSON_FILE__' => var_export($jsonFile, true),
    '__STATE_FILE__' => var_export($stateFile, true),
    '__USERS_FILE__' => var_export($usersFile, true),
    '__MANUAL_FILE__' => var_export($manualFile, true),
    '__CACHE_FILE__' => var_export($cacheFile, true),
]);

$historyPreviewPayload = run_php_json($historyPreviewCode);
assert_same(true, $historyPreviewPayload['ok'] ?? false, 'History preview should return ok=true.');
assert_same('history', $historyPreviewPayload['meta']['mode'] ?? null, 'History preview should expose history mode.');
assert_same(true, $historyPreviewPayload['meta']['date_filter']['applied'] ?? false, 'History preview should expose an applied date filter.');
assert_same('2025-12-10', $historyPreviewPayload['meta']['date_filter']['from'] ?? null, 'History preview should expose the start date.');
assert_same('2025-12-20', $historyPreviewPayload['meta']['date_filter']['to'] ?? null, 'History preview should expose the end date.');
assert_same(3, count($historyPreviewPayload['rows'] ?? []), 'History preview should merge local rows plus the deduplicated remote rows in range.');

$historyPreviewById = [];
foreach (($historyPreviewPayload['rows'] ?? []) as $row) {
    $historyPreviewById[(string) ($row['id'] ?? '')] = $row;
}

assert_true(isset($historyPreviewById['legacy-row']) || isset($historyPreviewById['json_legacy-row']), 'Legacy monthly rows inside the range should remain visible.');
assert_true(isset($historyPreviewById['api_111_home']), 'History preview should prefer fresh remote rows for the matched home side.');
assert_true(isset($historyPreviewById['api_111_away']), 'History preview should include the away side from the remote response.');
assert_true(!isset($historyPreviewById['json_outside-exact-range']), 'Rows with exact kickoff outside the requested range should be excluded.');

$authCode = strtr(<<<'PHP'
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['pricejust_user'] = 'admin';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'sync_history';
$GLOBALS['__PJ_REQUEST_BODY'] = [
    'reset' => true,
    'date_from' => '2025-12-10',
    'date_to' => '2025-12-20',
];
$GLOBALS['__PJ_FILE_OVERRIDES'] = [
    'users' => __USERS_FILE__,
    'manual' => __MANUAL_FILE__,
    'cache' => __CACHE_FILE__,
    'json_source' => __JSON_FILE__,
    'history_sync_state' => __STATE_FILE__,
];
$GLOBALS['__PJ_CONFIG_OVERRIDE'] = [
    'auth' => [
        'username' => 'admin',
    ],
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
        if (($query['from'] ?? '') === '' || ($query['to'] ?? '') === '') {
            throw new RuntimeException('Historical events should receive a date range.');
        }
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
    '__USERS_FILE__' => var_export($usersFile, true),
    '__MANUAL_FILE__' => var_export($manualFile, true),
    '__CACHE_FILE__' => var_export($cacheFile, true),
]);

$payload = run_php_json($authCode);
assert_same(true, $payload['ok'] ?? false, 'Authenticated sync_history call should return ok=true.');
assert_same('completed', $payload['state'] ?? null, 'Authenticated sync_history call should complete with the mocked payload.');
assert_same(1, (int) ($payload['counts']['inserted'] ?? -1), 'sync_history should insert only the missing managed row from the mocked event.');
assert_same(1, (int) ($payload['counts']['updated'] ?? -1), 'sync_history should update the existing managed row when it already exists.');
assert_same(1, (int) ($payload['counts']['skipped'] ?? -1), 'sync_history should report skipped events without valid odds.');
assert_same(2, (int) ($payload['counts']['preserved'] ?? -1), 'sync_history should preserve local JSON rows that are not managed by the sync.');
assert_true(is_array($payload['progress'] ?? null), 'sync_history should expose structured progress data.');
assert_same(1, (int) ($payload['progress']['total'] ?? -1), 'sync_history progress should count the mocked league/window task.');
assert_same(100, (int) ($payload['progress']['percent'] ?? -1), 'sync_history progress should reach 100 percent when completed.');
assert_same('2025-12-10', $payload['date_filter']['from'] ?? null, 'sync_history should expose the start date filter.');
assert_same('2025-12-20', $payload['date_filter']['to'] ?? null, 'sync_history should expose the end date filter.');

$storedRows = json_decode((string) file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR);
assert_same(4, count($storedRows), 'dados.json should contain preserved rows plus the managed API rows.');

$storedById = [];
foreach ($storedRows as $row) {
    $storedById[(string) ($row['id'] ?? '')] = $row;
}

assert_true(isset($storedById['legacy-row']), 'Legacy rows should remain untouched after sync_history.');
assert_true(isset($storedById['outside-exact-range']), 'Rows outside the exact range should remain untouched after sync_history.');
assert_true(isset($storedById['oddsapi_111_home']), 'sync_history should persist the deterministic home id.');
assert_true(isset($storedById['oddsapi_111_away']), 'sync_history should persist the deterministic away id.');
assert_same('2025-12', $storedById['oddsapi_111_home']['date'] ?? null, 'Historical sync rows should persist the month label only.');
assert_same('1.7', $storedById['oddsapi_111_home']['odd_ht'] ?? null, 'Historical sync rows should persist HT odds when available.');
assert_same('', $storedById['oddsapi_111_away']['odd_ht'] ?? null, 'Historical sync rows should keep empty HT odds when the market side is unavailable.');
assert_same(111, (int) ($storedById['oddsapi_111_home']['fixture_id'] ?? 0), 'Historical sync rows should persist fixture_id.');
assert_same('2025-12-14T19:00:00Z', $storedById['oddsapi_111_home']['kickoff_at'] ?? null, 'Historical sync rows should persist kickoff_at.');
assert_same('FT', $storedById['oddsapi_111_home']['match_status'] ?? null, 'Historical sync rows should persist match_status.');
assert_same('Bet365', $storedById['oddsapi_111_home']['bookmaker'] ?? null, 'Historical sync rows should persist bookmaker.');
assert_same('Away FC', $storedById['oddsapi_111_home']['opponent'] ?? null, 'Historical sync rows should persist opponent.');

$statePayload = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
assert_same('completed', $statePayload['status'] ?? null, 'History sync state cache should persist the completed status.');
assert_same('2025-12-10', $statePayload['date_filter']['from'] ?? null, 'History sync state cache should persist the start date.');
assert_same('2025-12-20', $statePayload['date_filter']['to'] ?? null, 'History sync state cache should persist the end date.');

echo "API history sync checks passed.\n";
