<?php

declare(strict_types=1);

require __DIR__ . '/../price_data/lib/bootstrap.php';

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

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(
            $message
            . ' Missing fragment: ' . var_export($needle, true)
            . ' Actual: ' . var_export($haystack, true)
        );
    }
}

$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flashradar-history-sync-tests';
if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
    throw new RuntimeException('Unable to create temp directory for history sync checks.');
}

$jsonFile = $tempDir . DIRECTORY_SEPARATOR . 'dados.json';
$stateFile = $tempDir . DIRECTORY_SEPARATOR . 'history-sync.json';
$manualFile = $tempDir . DIRECTORY_SEPARATOR . 'manual.json';
$cacheFile = $tempDir . DIRECTORY_SEPARATOR . 'dashboard-cache.json';

file_put_contents($jsonFile, json_encode([
    [
        'category' => 'Jogo parelho',
        'date' => '2026-01',
        'id' => 'legacy-row',
        'league' => 'Legacy League',
        'odd_ft' => '3.2',
        'odd_ht' => '2.3',
        'score_ht' => '0x0',
        'team' => 'Legacy FC',
        'venue' => 'Casa',
    ],
    [
        'category' => 'Jogo parelho',
        'date' => '2026-01',
        'id' => 'oddsapi_111_home',
        'league' => 'League A',
        'odd_ft' => '2.1',
        'odd_ht' => '1.5',
        'score_ht' => '0x0',
        'team' => 'Old Home',
        'venue' => 'Casa',
        'fixture_id' => 111,
        'kickoff_at' => '2026-01-14T19:00:00Z',
        'match_status' => 'FT',
        'bookmaker' => 'Old Bookmaker',
        'opponent' => 'Old Away',
    ],
    [
        'category' => 'Jogo parelho',
        'date' => '2026-01',
        'id' => 'precise-outside-range',
        'league' => 'League A',
        'odd_ft' => '4.1',
        'odd_ht' => '2.9',
        'score_ht' => '1x1',
        'team' => 'Outside Exact FC',
        'venue' => 'Casa',
        'kickoff_at' => '2026-01-28T19:00:00Z',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$GLOBALS['__PJ_FILE_OVERRIDES'] = [
    'manual' => $manualFile,
    'cache' => $cacheFile,
    'json_source' => $jsonFile,
    'history_sync_state' => $stateFile,
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

$httpCalls = [];
$GLOBALS['__PJ_HTTP_JSON_OVERRIDE'] = static function (string $method, string $url, array $headers) use (&$httpCalls): array {
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';
    $queryString = $parsed['query'] ?? '';
    parse_str($queryString, $query);

    $httpCalls[] = [
        'method' => $method,
        'path' => $path,
        'query' => $query,
    ];

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
        assert_same('football', $query['sport'] ?? null, 'Historical events should request the configured sport.');
        assert_same('league-a', $query['league'] ?? null, 'Historical events should request the league slug.');

        return [
            [
                'id' => 111,
                'home' => 'Home FC',
                'away' => 'Away FC',
                'date' => '2026-01-14T19:00:00Z',
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
                'date' => '2026-01-15T19:00:00Z',
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
                'date' => '2026-01-14T19:00:00Z',
                'status' => 'finished',
                'bookmakers' => [
                    'Bet365' => [
                        [
                            'name' => 'ML',
                            'odds' => [
                                [
                                    'home' => '2.4',
                                    'draw' => '3.2',
                                    'away' => '2.8',
                                ],
                            ],
                        ],
                        [
                            'name' => 'Half Time Result',
                            'odds' => [
                                [
                                    'label' => '1',
                                    'under' => '1.7',
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
                'date' => '2026-01-15T19:00:00Z',
                'status' => 'finished',
                'bookmakers' => [],
            ];
        }
    }

    throw new RuntimeException('Unexpected mocked request: ' . $path . '?' . $queryString);
};

$windows = pj_history_sync_build_windows(
    new DateTimeImmutable('2025-12-01T00:00:00-03:00'),
    new DateTimeImmutable('2026-02-20T23:59:59-03:00')
);
assert_same(3, count($windows), 'Historical sync should split ranges into 31-day windows.');
assert_true(isset($windows[0]['from'], $windows[0]['to']), 'Each window should expose from/to values.');

$payload = pj_history_sync_step(true, '2026-01-10', '2026-01-20');
assert_same(true, $payload['ok'] ?? false, 'First history sync step should return ok=true.');
assert_same('completed', $payload['state'] ?? null, 'Single mocked league/window should complete in one step.');
assert_same(1, (int) ($payload['counts']['inserted'] ?? -1), 'Should insert the away row from the synced event.');
assert_same(1, (int) ($payload['counts']['updated'] ?? -1), 'Should update the existing managed row with the same deterministic id.');
assert_same(1, (int) ($payload['counts']['skipped'] ?? -1), 'Should skip events without valid odds.');
assert_same(2, (int) ($payload['counts']['preserved'] ?? -1), 'Should preserve local rows that are not managed by the sync.');
assert_same(4, (int) ($payload['counts']['total_json_rows'] ?? -1), 'Merged JSON row count should include preserved + managed rows.');
assert_same(true, $payload['date_filter']['applied'] ?? false, 'History sync payload should expose the applied date filter.');
assert_same('2026-01-10', $payload['date_filter']['from'] ?? null, 'History sync payload should expose the start date.');
assert_same('2026-01-20', $payload['date_filter']['to'] ?? null, 'History sync payload should expose the end date.');

$savedRows = json_decode((string) file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR);
assert_same(4, count($savedRows), 'dados.json should be rewritten with the merged rows.');

$byId = [];
foreach ($savedRows as $row) {
    $byId[(string) ($row['id'] ?? '')] = $row;
}

assert_true(isset($byId['legacy-row']), 'Legacy rows should remain present in dados.json.');
assert_true(isset($byId['precise-outside-range']), 'Rows outside the exact range should remain untouched.');
assert_true(isset($byId['oddsapi_111_home']), 'Home row should use the deterministic managed id.');
assert_true(isset($byId['oddsapi_111_away']), 'Away row should use the deterministic managed id.');
assert_same('2026-01', $byId['oddsapi_111_home']['date'] ?? null, 'Historical rows should persist the month label only.');
assert_same('Home FC', $byId['oddsapi_111_home']['team'] ?? null, 'Managed rows should preserve the team name.');
assert_same('Away FC', $byId['oddsapi_111_away']['team'] ?? null, 'Managed rows should preserve the opponent side.');
assert_same('2x1', $byId['oddsapi_111_home']['score_ht'] ?? null, 'Historical rows should persist the returned score label.');
assert_same('2.4', $byId['oddsapi_111_home']['odd_ft'] ?? null, 'Historical rows should persist the FT odd.');
assert_same('1.7', $byId['oddsapi_111_home']['odd_ht'] ?? null, 'Historical rows should persist the HT odd when available.');
assert_same('', $byId['oddsapi_111_away']['odd_ht'] ?? null, 'Historical rows should keep HT empty when the market side is missing.');
assert_same(111, (int) ($byId['oddsapi_111_home']['fixture_id'] ?? 0), 'Historical rows should persist fixture_id.');
assert_same('2026-01-14T19:00:00Z', $byId['oddsapi_111_home']['kickoff_at'] ?? null, 'Historical rows should persist kickoff_at.');
assert_same('FT', $byId['oddsapi_111_home']['match_status'] ?? null, 'Historical rows should persist match_status.');
assert_same('Bet365', $byId['oddsapi_111_home']['bookmaker'] ?? null, 'Historical rows should persist bookmaker.');
assert_same('Away FC', $byId['oddsapi_111_home']['opponent'] ?? null, 'Historical rows should persist opponent.');

$completedPayload = pj_history_sync_step(false, '2026-01-10', '2026-01-20');
assert_same('completed', $completedPayload['state'] ?? null, 'Completed sync state should be resumable without resetting.');
assert_same(
    (int) ($payload['counts']['total_json_rows'] ?? -1),
    (int) ($completedPayload['counts']['total_json_rows'] ?? -1),
    'Completed state should preserve the last counters.'
);

$historicalEventCalls = array_values(array_filter($httpCalls, static fn (array $call): bool => $call['path'] === '/v3/historical/events'));
assert_same(1, count($historicalEventCalls), 'History sync should fetch historical events once for the mocked range.');
assert_contains('2026-01-10', (string) ($historicalEventCalls[0]['query']['from'] ?? ''), 'Historical sync should start from the requested start date.');
assert_contains('2026-01-20', (string) ($historicalEventCalls[0]['query']['to'] ?? ''), 'Historical sync should stop at the requested end date.');

$statePayload = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
assert_same('2026-01-10', $statePayload['date_filter']['from'] ?? null, 'History sync state should cache the start date.');
assert_same('2026-01-20', $statePayload['date_filter']['to'] ?? null, 'History sync state should cache the end date.');

echo "Bootstrap history sync checks passed.\n";
