<?php

declare(strict_types=1);

const PRICEJUST_SESSION_KEY = 'pricejust_user';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function pj_root_path(string $relative = ''): string
{
    $base = dirname(__DIR__);
    if ($relative === '') {
        return $base;
    }

    return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function pj_repo_path(string $relative = ''): string
{
    $base = dirname(pj_root_path());
    if ($relative === '') {
        return $base;
    }

    return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function pj_storage_path(string $relative = ''): string
{
    return pj_root_path('storage' . ($relative !== '' ? DIRECTORY_SEPARATOR . $relative : ''));
}

function pj_read_json_file(string $file, mixed $default): mixed
{
    if (!is_file($file)) {
        return $default;
    }

    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}

function pj_write_json_file(string $file, mixed $payload): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to encode JSON payload.');
    }

    $handle = fopen($file, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open storage file.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock storage file.');
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function pj_request_body(): array
{
    if (PHP_SAPI === 'cli' && isset($GLOBALS['__PJ_REQUEST_BODY']) && is_array($GLOBALS['__PJ_REQUEST_BODY'])) {
        return $GLOBALS['__PJ_REQUEST_BODY'];
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function pj_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function pj_load_config_file(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $loaded = require $file;

    return is_array($loaded) ? $loaded : [];
}

function pj_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $default = [
        'auth' => [
            'username' => 'admin',
            'password_hash' => '',
        ],
        'api' => [
            'key' => '',
            'base_url' => 'https://v3.football.api-sports.io',
            'timezone' => 'America/Sao_Paulo',
            'cache_ttl_seconds' => 60,
            'bookmaker_priority' => ['Bet365', 'Betano', 'Pinnacle', '1xBet'],
        ],
        'thresholds' => [
            'parelho_max' => 3.8,
            'super_min' => 6.2,
        ],
        'dashboard' => [
            'refresh_seconds' => 60,
        ],
    ];

    $example = pj_load_config_file(pj_root_path('config.example.php'));
    $local = pj_load_config_file(pj_root_path('config.local.php'));
    $config = array_replace_recursive($default, $example, $local);

    return $config;
}

function pj_is_authenticated(): bool
{
    return is_string($_SESSION[PRICEJUST_SESSION_KEY] ?? null) && $_SESSION[PRICEJUST_SESSION_KEY] !== '';
}

function pj_auth_username(): ?string
{
    return pj_is_authenticated() ? (string) $_SESSION[PRICEJUST_SESSION_KEY] : null;
}

function pj_login(string $username): void
{
    $_SESSION[PRICEJUST_SESSION_KEY] = $username;
    session_regenerate_id(true);
}

function pj_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function pj_require_auth_json(): void
{
    if (!pj_is_authenticated()) {
        pj_json_response([
            'ok' => false,
            'error' => 'AUTH_REQUIRED',
        ], 401);
    }
}

function pj_require_auth_page(): void
{
    if (!pj_is_authenticated()) {
        header('Location: login.php');
        exit;
    }
}

function pj_manual_file(): string
{
    return pj_storage_path('manual.json');
}

function pj_cache_file(): string
{
    return pj_storage_path('cache/dashboard.json');
}

function pj_manual_rows(): array
{
    $rows = pj_read_json_file(pj_manual_file(), []);
    if (!is_array($rows)) {
        return [];
    }

    $normalized = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $normalized[] = pj_normalize_manual_row($row);
    }

    return $normalized;
}

function pj_normalize_manual_row(array $row): array
{
    $id = trim((string) ($row['id'] ?? ''));
    if ($id === '') {
        $id = 'manual_' . bin2hex(random_bytes(8));
    }

    return [
        'id' => $id,
        'league' => trim((string) ($row['league'] ?? '')),
        'category' => trim((string) ($row['category'] ?? '')),
        'team' => trim((string) ($row['team'] ?? '')),
        'venue' => trim((string) ($row['venue'] ?? '')),
        'date' => trim((string) ($row['date'] ?? '')),
        'odd_ht' => pj_string_number_or_empty($row['odd_ht'] ?? ''),
        'score_ht' => trim((string) ($row['score_ht'] ?? '')),
        'odd_ft' => pj_string_number_or_empty($row['odd_ft'] ?? ''),
        'opponent' => trim((string) ($row['opponent'] ?? '')),
        'source' => 'manual',
        'fixture_id' => null,
        'match_status' => 'MANUAL',
        'elapsed_min' => null,
        'kickoff_at' => null,
        'bookmaker' => null,
    ];
}

function pj_string_number_or_empty(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }

    return is_numeric($text) ? (string) (float) $text : $text;
}

function pj_validate_manual_payload(array $payload): array
{
    $required = ['league', 'category', 'team', 'venue', 'date', 'score_ht', 'odd_ft'];
    foreach ($required as $field) {
        if (trim((string) ($payload[$field] ?? '')) === '') {
            throw new InvalidArgumentException("Missing field: {$field}");
        }
    }

    $venue = trim((string) ($payload['venue'] ?? ''));
    if (!in_array($venue, ['Casa', 'Fora'], true)) {
        throw new InvalidArgumentException('Venue must be Casa or Fora.');
    }

    return pj_normalize_manual_row($payload);
}

function pj_category_for_odd(float $oddFt): string
{
    $thresholds = pj_config()['thresholds'] ?? [];
    $parelhoMax = (float) ($thresholds['parelho_max'] ?? 3.8);
    $superMin = (float) ($thresholds['super_min'] ?? 6.2);

    if ($oddFt < $parelhoMax) {
        return 'Jogo parelho';
    }

    if ($oddFt >= $superMin) {
        return 'Jogo de super favorito';
    }

    return 'Jogo de favorito';
}

function pj_has_api_key(): bool
{
    $key = trim((string) (pj_config()['api']['key'] ?? ''));

    return $key !== '' && $key !== 'PASTE_API_FOOTBALL_KEY_HERE';
}

function pj_http_json(string $method, string $url, array $headers = []): array
{
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $caFile = pj_repo_path('tools/php/cacert.pem');
        if (is_file($caFile)) {
            $curlOptions[CURLOPT_CAINFO] = $caFile;
        }

        curl_setopt_array($ch, $curlOptions);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => 20,
                'header' => implode("\r\n", $headerLines),
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new RuntimeException('HTTP request failed.');
        }

        $status = 200;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/\s(\d{3})\s/', $header, $matches)) {
                $status = (int) $matches[1];
                break;
            }
        }
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON response from upstream.');
    }

    if ($status >= 400) {
        throw new RuntimeException('Upstream returned HTTP ' . $status);
    }

    return $decoded;
}

function pj_http_json_all_pages(string $url, array $headers = []): array
{
    $firstPayload = pj_http_json('GET', $url, $headers);
    $allResponse = is_array($firstPayload['response'] ?? null) ? $firstPayload['response'] : [];
    $paging = $firstPayload['paging'] ?? [];
    $totalPages = max(1, (int) ($paging['total'] ?? 1));

    if ($totalPages <= 1) {
        return $firstPayload;
    }

    for ($page = 2; $page <= $totalPages; $page++) {
        $separator = str_contains($url, '?') ? '&' : '?';
        $payload = pj_http_json('GET', $url . $separator . 'page=' . $page, $headers);
        $chunk = $payload['response'] ?? [];
        if (is_array($chunk)) {
            $allResponse = array_merge($allResponse, $chunk);
        }
    }

    $firstPayload['response'] = $allResponse;
    $firstPayload['paging'] = [
        'current' => $totalPages,
        'total' => $totalPages,
    ];

    return $firstPayload;
}

function pj_format_kickoff_for_table(?string $isoString, string $timezone): string
{
    if (!$isoString) {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($isoString);
        $local = $dt->setTimezone(new DateTimeZone($timezone));
        return $local->format('Y-m-d H:i');
    } catch (Throwable) {
        return '';
    }
}

function pj_status_code(array $fixture): string
{
    return strtoupper((string) ($fixture['fixture']['status']['short'] ?? ''));
}

function pj_elapsed_minutes(array $fixture): ?int
{
    $elapsed = $fixture['fixture']['status']['elapsed'] ?? null;
    if ($elapsed === null || $elapsed === '') {
        return null;
    }

    return is_numeric($elapsed) ? (int) $elapsed : null;
}

function pj_half_time_score(array $fixture): string
{
    $home = $fixture['score']['halftime']['home'] ?? null;
    $away = $fixture['score']['halftime']['away'] ?? null;

    if ($home === null || $away === null) {
        return '';
    }

    return (string) $home . 'x' . (string) $away;
}

function pj_fixture_date_iso(array $fixture): ?string
{
    $date = $fixture['fixture']['date'] ?? null;
    return is_string($date) && $date !== '' ? $date : null;
}

function pj_find_market(array $bookmaker, array $aliases): ?array
{
    $markets = $bookmaker['markets'] ?? $bookmaker['bets'] ?? $bookmaker['odds'] ?? [];
    foreach ($markets as $bet) {
        $name = strtolower(trim((string) ($bet['name'] ?? '')));
        foreach ($aliases as $alias) {
            if ($name === strtolower($alias)) {
                return $bet;
            }
        }
    }

    return null;
}

function pj_market_odd_for_side(?array $market, string $side, string $teamName): ?float
{
    if (!$market) {
        return null;
    }

    $aliases = $side === 'home'
        ? ['home', '1', strtolower($teamName)]
        : ['away', '2', strtolower($teamName)];

    foreach ($market['values'] ?? [] as $value) {
        $label = strtolower(trim((string) ($value['value'] ?? '')));
        if (!in_array($label, $aliases, true)) {
            continue;
        }

        $odd = $value['odd'] ?? null;
        if ($odd !== null && is_numeric((string) $odd)) {
            return (float) $odd;
        }
    }

    return null;
}

function pj_bookmaker_has_team_markets(array $bookmaker, string $homeName, string $awayName): array
{
    $ftAliases = ['match winner', 'match winner 1x2', '1x2', 'winner', 'fulltime result', 'full time result', 'result'];
    $htAliases = ['halftime result', 'half time result', '1st half winner', 'first half winner', '1x2 (1st half)', '1x2 1st half', 'first half result'];

    $ftMarket = pj_find_market($bookmaker, $ftAliases);
    $htMarket = pj_find_market($bookmaker, $htAliases);

    return [
        'ft' => [
            'home' => pj_market_odd_for_side($ftMarket, 'home', $homeName),
            'away' => pj_market_odd_for_side($ftMarket, 'away', $awayName),
        ],
        'ht' => [
            'home' => pj_market_odd_for_side($htMarket, 'home', $homeName),
            'away' => pj_market_odd_for_side($htMarket, 'away', $awayName),
        ],
    ];
}

function pj_normalize_odds_sources(array $oddsEntry): array
{
    $sources = [];

    foreach ($oddsEntry['bookmakers'] ?? [] as $bookmaker) {
        if (!is_array($bookmaker)) {
            continue;
        }

        $sources[] = [
            'name' => (string) ($bookmaker['name'] ?? ''),
            'markets' => is_array($bookmaker['bets'] ?? null) ? $bookmaker['bets'] : [],
        ];
    }

    if (is_array($oddsEntry['odds'] ?? null) && $oddsEntry['odds'] !== []) {
        $sources[] = [
            'name' => (string) ($oddsEntry['provider'] ?? 'Live Odds'),
            'markets' => $oddsEntry['odds'],
        ];
    }

    return $sources;
}

function pj_select_bookmaker(array $oddsEntry, string $homeName, string $awayName): array
{
    $priority = pj_config()['api']['bookmaker_priority'] ?? [];
    $sources = pj_normalize_odds_sources($oddsEntry);
    if ($sources === []) {
        return [];
    }

    $ordered = [];
    $used = [];
    foreach ($priority as $wanted) {
        foreach ($sources as $index => $bookmaker) {
            if (($used[$index] ?? false) === true) {
                continue;
            }

            if (strcasecmp((string) ($bookmaker['name'] ?? ''), (string) $wanted) === 0) {
                $ordered[] = $bookmaker;
                $used[$index] = true;
            }
        }
    }
    foreach ($sources as $index => $bookmaker) {
        if (($used[$index] ?? false) === true) {
            continue;
        }

        $ordered[] = $bookmaker;
    }

    $bestFtOnly = null;
    foreach ($ordered as $bookmaker) {
        $marketSet = pj_bookmaker_has_team_markets($bookmaker, $homeName, $awayName);
        $hasFt = $marketSet['ft']['home'] !== null && $marketSet['ft']['away'] !== null;
        $hasHt = $marketSet['ht']['home'] !== null && $marketSet['ht']['away'] !== null;

        if ($hasFt && $hasHt) {
            return [
                'bookmaker' => (string) ($bookmaker['name'] ?? 'Live Odds'),
                'ft' => $marketSet['ft'],
                'ht' => $marketSet['ht'],
            ];
        }

        if ($hasFt && $bestFtOnly === null) {
            $bestFtOnly = [
                'bookmaker' => (string) ($bookmaker['name'] ?? 'Live Odds'),
                'ft' => $marketSet['ft'],
                'ht' => $marketSet['ht'],
            ];
        }
    }

    return $bestFtOnly ?? [];
}

function pj_fetch_api_rows(): array
{
    $config = pj_config();
    if (!pj_has_api_key()) {
        return [
            'rows' => [],
            'meta' => [
                'stale' => false,
                'configured' => false,
                'fetched_at' => null,
                'warning' => 'Modo manual ativo. Configure api.key em config.local.php para carregar odds da API.',
            ],
        ];
    }

    $cacheFile = pj_cache_file();
    $cache = pj_read_json_file($cacheFile, []);
    $cacheTtl = (int) ($config['api']['cache_ttl_seconds'] ?? 60);
    $now = time();
    $cachedAt = (int) ($cache['cached_at'] ?? 0);

    if ($cachedAt > 0 && ($now - $cachedAt) < $cacheTtl && is_array($cache['rows'] ?? null)) {
        return [
            'rows' => $cache['rows'],
            'meta' => [
                'stale' => false,
                'configured' => true,
                'fetched_at' => $cache['fetched_at'] ?? null,
                'warning' => null,
            ],
        ];
    }

    $headers = [
        'x-apisports-key' => (string) $config['api']['key'],
        'Accept' => 'application/json',
    ];
    $baseUrl = rtrim((string) ($config['api']['base_url'] ?? ''), '/');
    $timezone = (string) ($config['api']['timezone'] ?? 'America/Sao_Paulo');
    $today = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d');

    try {
        $liveFixtures = pj_http_json_all_pages($baseUrl . '/fixtures?live=all&timezone=' . rawurlencode($timezone), $headers);
        $todayFixtures = pj_http_json_all_pages($baseUrl . '/fixtures?date=' . rawurlencode($today) . '&timezone=' . rawurlencode($timezone), $headers);
        $liveOdds = pj_http_json_all_pages($baseUrl . '/odds/live', $headers);
        $todayOdds = pj_http_json_all_pages($baseUrl . '/odds?date=' . rawurlencode($today), $headers);

        $fixturesById = [];
        foreach ([$todayFixtures, $liveFixtures] as $payload) {
            foreach (($payload['response'] ?? []) as $fixture) {
                $fixtureId = (int) ($fixture['fixture']['id'] ?? 0);
                if ($fixtureId > 0) {
                    $fixturesById[$fixtureId] = $fixture;
                }
            }
        }

        $oddsByFixture = [];
        foreach ([$todayOdds, $liveOdds] as $payload) {
            foreach (($payload['response'] ?? []) as $entry) {
                $fixtureId = (int) (($entry['fixture']['id'] ?? $entry['fixture_id'] ?? 0));
                if ($fixtureId > 0) {
                    $oddsByFixture[$fixtureId] = $entry;
                }
            }
        }

        $rows = [];
        foreach ($fixturesById as $fixtureId => $fixture) {
            $homeName = trim((string) ($fixture['teams']['home']['name'] ?? ''));
            $awayName = trim((string) ($fixture['teams']['away']['name'] ?? ''));
            if ($homeName === '' || $awayName === '') {
                continue;
            }

            $bookmakerSelection = pj_select_bookmaker($oddsByFixture[$fixtureId] ?? [], $homeName, $awayName);
            if ($bookmakerSelection === []) {
                continue;
            }

            $homeFt = $bookmakerSelection['ft']['home'] ?? null;
            $awayFt = $bookmakerSelection['ft']['away'] ?? null;
            if (!is_float($homeFt) || !is_float($awayFt)) {
                continue;
            }

            $scoreHt = pj_half_time_score($fixture);
            $dateLabel = pj_format_kickoff_for_table(pj_fixture_date_iso($fixture), $timezone);
            $status = pj_status_code($fixture);
            $elapsed = pj_elapsed_minutes($fixture);
            $league = trim((string) ($fixture['league']['name'] ?? ''));
            $bookmakerName = (string) ($bookmakerSelection['bookmaker'] ?? '');
            $kickoffAt = pj_fixture_date_iso($fixture);

            $rows[] = [
                'id' => 'api_' . $fixtureId . '_home',
                'league' => $league,
                'category' => pj_category_for_odd($homeFt),
                'team' => $homeName,
                'venue' => 'Casa',
                'date' => $dateLabel,
                'odd_ht' => isset($bookmakerSelection['ht']['home']) && is_float($bookmakerSelection['ht']['home']) ? (string) $bookmakerSelection['ht']['home'] : '',
                'score_ht' => $scoreHt,
                'odd_ft' => (string) $homeFt,
                'opponent' => $awayName,
                'source' => 'api',
                'fixture_id' => $fixtureId,
                'match_status' => $status,
                'elapsed_min' => $elapsed,
                'kickoff_at' => $kickoffAt,
                'bookmaker' => $bookmakerName,
            ];

            $rows[] = [
                'id' => 'api_' . $fixtureId . '_away',
                'league' => $league,
                'category' => pj_category_for_odd($awayFt),
                'team' => $awayName,
                'venue' => 'Fora',
                'date' => $dateLabel,
                'odd_ht' => isset($bookmakerSelection['ht']['away']) && is_float($bookmakerSelection['ht']['away']) ? (string) $bookmakerSelection['ht']['away'] : '',
                'score_ht' => $scoreHt,
                'odd_ft' => (string) $awayFt,
                'opponent' => $homeName,
                'source' => 'api',
                'fixture_id' => $fixtureId,
                'match_status' => $status,
                'elapsed_min' => $elapsed,
                'kickoff_at' => $kickoffAt,
                'bookmaker' => $bookmakerName,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($a['league'] ?? ''), (string) ($b['league'] ?? ''))
                ?: strcmp((string) ($a['category'] ?? ''), (string) ($b['category'] ?? ''))
                ?: strcmp((string) ($a['team'] ?? ''), (string) ($b['team'] ?? ''));
        });

        $snapshot = [
            'cached_at' => $now,
            'fetched_at' => gmdate('c'),
            'rows' => $rows,
        ];
        pj_write_json_file($cacheFile, $snapshot);

        return [
            'rows' => $rows,
            'meta' => [
                'stale' => false,
                'configured' => true,
                'fetched_at' => $snapshot['fetched_at'],
                'warning' => null,
            ],
        ];
    } catch (Throwable $error) {
        if (is_array($cache['rows'] ?? null)) {
            return [
                'rows' => $cache['rows'],
                'meta' => [
                    'stale' => true,
                    'configured' => true,
                    'fetched_at' => $cache['fetched_at'] ?? null,
                    'warning' => $error->getMessage(),
                ],
            ];
        }

        return [
            'rows' => [],
            'meta' => [
                'stale' => true,
                'configured' => true,
                'fetched_at' => null,
                'warning' => $error->getMessage(),
            ],
        ];
    }
}
