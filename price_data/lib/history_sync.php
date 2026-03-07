<?php

declare(strict_types=1);

function pj_history_sync_prefix(): string
{
    return 'oddsapi_';
}

function pj_history_sync_max_events_per_step(): int
{
    return 8;
}

function pj_history_sync_empty_counts(int $preserved = 0, int $totalRows = 0): array
{
    return [
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'preserved' => max(0, $preserved),
        'total_json_rows' => max(0, $totalRows),
    ];
}

function pj_history_sync_read_source_rows(): array
{
    $file = pj_json_source_file();
    if (!is_file($file)) {
        return [];
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Arquivo dados.json invalido: ' . json_last_error_msg() . '.');
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('Arquivo dados.json deve conter uma lista de registros.');
    }

    $rows = [];
    foreach ($decoded as $row) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }

    return array_values($rows);
}

function pj_history_sync_is_managed_id(string $id): bool
{
    return $id !== '' && str_starts_with($id, pj_history_sync_prefix());
}

function pj_history_sync_count_preserved_rows(array $rows): int
{
    $count = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = trim((string) ($row['id'] ?? ''));
        if (!pj_history_sync_is_managed_id($id)) {
            $count++;
        }
    }

    return $count;
}

function pj_history_sync_history_start(string $timezone): DateTimeImmutable
{
    return new DateTimeImmutable('2025-12-01 00:00:00', new DateTimeZone($timezone));
}

function pj_history_sync_now(string $timezone): DateTimeImmutable
{
    $override = $GLOBALS['__PJ_HISTORY_SYNC_NOW'] ?? null;
    if (is_string($override) && trim($override) !== '') {
        try {
            return new DateTimeImmutable($override, new DateTimeZone($timezone));
        } catch (Throwable) {
        }
    }

    return new DateTimeImmutable('now', new DateTimeZone($timezone));
}

function pj_history_sync_build_windows(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    if ($end < $start) {
        return [];
    }

    $windows = [];
    $cursor = $start;
    while ($cursor <= $end) {
        $windowEnd = $cursor->modify('+30 days')->setTime(23, 59, 59);
        if ($windowEnd > $end) {
            $windowEnd = $end;
        }

        $windows[] = [
            'from' => $cursor->format(DateTimeInterface::RFC3339),
            'to' => $windowEnd->format(DateTimeInterface::RFC3339),
        ];

        $cursor = $windowEnd->modify('+1 second');
    }

    return $windows;
}

function pj_history_sync_fetch_leagues(): array
{
    $sport = (string) (pj_config()['api']['sport'] ?? 'football');
    $payload = pj_odds_api_get('/leagues', [
        'sport' => $sport,
        'all' => 'true',
    ]);

    $leagues = [];
    foreach ($payload as $league) {
        if (!is_array($league)) {
            continue;
        }

        $name = trim((string) ($league['name'] ?? ''));
        $slug = trim((string) ($league['slug'] ?? ''));
        $leagueSport = trim((string) ($league['sport']['slug'] ?? $league['sport'] ?? ''));

        if ($name === '' || $slug === '') {
            continue;
        }

        if ($leagueSport !== '' && strcasecmp($leagueSport, $sport) !== 0) {
            continue;
        }

        $leagues[$slug] = [
            'name' => $name,
            'slug' => $slug,
        ];
    }

    uasort($leagues, static function (array $a, array $b): int {
        return strcmp($a['name'], $b['name']) ?: strcmp($a['slug'], $b['slug']);
    });

    return array_values($leagues);
}

function pj_history_sync_build_tasks(array $leagues, array $windows): array
{
    $tasks = [];
    foreach ($leagues as $league) {
        if (!is_array($league)) {
            continue;
        }

        $leagueName = trim((string) ($league['name'] ?? ''));
        $leagueSlug = trim((string) ($league['slug'] ?? ''));
        if ($leagueName === '' || $leagueSlug === '') {
            continue;
        }

        foreach ($windows as $window) {
            if (!is_array($window)) {
                continue;
            }

            $from = trim((string) ($window['from'] ?? ''));
            $to = trim((string) ($window['to'] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }

            $tasks[] = [
                'league_name' => $leagueName,
                'league_slug' => $leagueSlug,
                'from' => $from,
                'to' => $to,
            ];
        }
    }

    return $tasks;
}

function pj_history_sync_is_finished_status(mixed $status): bool
{
    return in_array(pj_odds_api_normalize_status($status), ['FT', 'AET', 'ET', 'PEN'], true);
}

function pj_history_sync_compact_event(array $event): ?array
{
    $eventId = (int) ($event['id'] ?? 0);
    $home = trim((string) ($event['home'] ?? ''));
    $away = trim((string) ($event['away'] ?? ''));
    if ($eventId <= 0 || $home === '' || $away === '') {
        return null;
    }

    if (!pj_history_sync_is_finished_status($event['status'] ?? '')) {
        return null;
    }

    $leagueName = trim((string) ($event['league']['name'] ?? ''));
    $leagueSlug = trim((string) ($event['league']['slug'] ?? ''));

    return [
        'id' => $eventId,
        'home' => $home,
        'away' => $away,
        'date' => pj_odds_api_event_date_iso($event),
        'status' => trim((string) ($event['status'] ?? 'finished')),
        'league' => [
            'name' => $leagueName,
            'slug' => $leagueSlug,
        ],
        'scores' => is_array($event['scores'] ?? null) ? $event['scores'] : [],
    ];
}

function pj_history_sync_fetch_events_for_task(array $task): array
{
    $sport = (string) (pj_config()['api']['sport'] ?? 'football');
    $payload = pj_odds_api_get('/historical/events', [
        'sport' => $sport,
        'league' => trim((string) ($task['league_slug'] ?? '')),
        'from' => trim((string) ($task['from'] ?? '')),
        'to' => trim((string) ($task['to'] ?? '')),
    ]);

    $events = [];
    foreach ($payload as $event) {
        if (!is_array($event)) {
            continue;
        }

        $compact = pj_history_sync_compact_event($event);
        if ($compact !== null) {
            if (($compact['league']['name'] ?? '') === '' && trim((string) ($task['league_name'] ?? '')) !== '') {
                $compact['league']['name'] = (string) $task['league_name'];
            }
            if (($compact['league']['slug'] ?? '') === '' && trim((string) ($task['league_slug'] ?? '')) !== '') {
                $compact['league']['slug'] = (string) $task['league_slug'];
            }
            $events[] = $compact;
        }
    }

    return $events;
}

function pj_history_sync_fetch_odds_for_event(int $eventId, array $bookmakers): array
{
    return pj_odds_api_get('/historical/odds', [
        'eventId' => $eventId,
        'bookmakers' => implode(',', $bookmakers),
    ]);
}

function pj_history_sync_row_id(int $fixtureId, string $venue): string
{
    $suffix = strcasecmp($venue, 'Fora') === 0 ? 'away' : 'home';
    return pj_history_sync_prefix() . $fixtureId . '_' . $suffix;
}

function pj_history_sync_month_label(?string $isoString, string $timezone): string
{
    if ($isoString === null || trim($isoString) === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($isoString);
        return $dt->setTimezone(new DateTimeZone($timezone))->format('Y-m');
    } catch (Throwable) {
        return '';
    }
}

function pj_history_sync_dashboard_row_to_json_row(array $row, string $timezone): array
{
    $fixtureId = (int) ($row['fixture_id'] ?? 0);
    $venue = trim((string) ($row['venue'] ?? 'Casa'));
    $oddFt = pj_string_number_or_empty($row['odd_ft'] ?? '');
    $category = trim((string) ($row['category'] ?? ''));
    if ($category === '' && $oddFt !== '' && is_numeric($oddFt)) {
        $category = pj_category_for_odd((float) $oddFt);
    }

    return [
        'category' => $category,
        'date' => pj_history_sync_month_label((string) ($row['kickoff_at'] ?? ''), $timezone),
        'id' => pj_history_sync_row_id($fixtureId, $venue),
        'league' => trim((string) ($row['league'] ?? '')),
        'odd_ft' => $oddFt,
        'odd_ht' => pj_string_number_or_empty($row['odd_ht'] ?? ''),
        'score_ht' => trim((string) ($row['score_ht'] ?? '')),
        'team' => trim((string) ($row['team'] ?? '')),
        'venue' => $venue === 'Fora' ? 'Fora' : 'Casa',
    ];
}

function pj_history_sync_rows_from_event(array $event, array $oddsEntry, string $timezone): array
{
    $dashboardRows = pj_build_rows_from_odds_event($event, $oddsEntry, $timezone);
    if ($dashboardRows === []) {
        return [];
    }

    $rows = [];
    foreach ($dashboardRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $fixtureId = (int) ($row['fixture_id'] ?? 0);
        if ($fixtureId <= 0) {
            continue;
        }

        $rows[] = pj_history_sync_dashboard_row_to_json_row($row, $timezone);
    }

    return $rows;
}

function pj_history_sync_merge_rows(array $incomingRows, array &$counts): int
{
    if ($incomingRows === []) {
        return (int) ($counts['total_json_rows'] ?? 0);
    }

    $existingRows = pj_history_sync_read_source_rows();
    $incomingById = [];
    foreach ($incomingRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $incomingById[$id] = $row;
    }

    if ($incomingById === []) {
        return count($existingRows);
    }

    $merged = [];
    $updated = 0;
    foreach ($existingRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '' && array_key_exists($id, $incomingById)) {
            $merged[] = $incomingById[$id];
            unset($incomingById[$id]);
            $updated++;
            continue;
        }

        $merged[] = $row;
    }

    $inserted = 0;
    foreach ($incomingById as $row) {
        $merged[] = $row;
        $inserted++;
    }

    $counts['updated'] = (int) ($counts['updated'] ?? 0) + $updated;
    $counts['inserted'] = (int) ($counts['inserted'] ?? 0) + $inserted;
    $counts['total_json_rows'] = count($merged);

    pj_write_json_file(pj_json_source_file(), array_values($merged));

    return count($merged);
}

function pj_history_sync_build_progress(array $state): array
{
    $tasks = is_array($state['tasks'] ?? null) ? $state['tasks'] : [];
    $total = count($tasks);
    $completed = min($total, max(0, (int) ($state['next_task_index'] ?? 0)));
    $pendingEvents = count(is_array($state['pending_events'] ?? null) ? $state['pending_events'] : []);
    $currentTask = is_array($state['current_task'] ?? null) ? $state['current_task'] : null;
    if ($currentTask === null && $completed < $total && isset($tasks[$completed]) && is_array($tasks[$completed])) {
        $currentTask = $tasks[$completed];
    }

    $percent = $total > 0 ? (int) floor(($completed / $total) * 100) : 100;
    if (($state['status'] ?? '') === 'completed') {
        $percent = 100;
    }

    return [
        'current' => $completed,
        'total' => $total,
        'percent' => $percent,
        'pending_events' => $pendingEvents,
        'current_league' => $currentTask['league_name'] ?? null,
        'current_range' => $currentTask !== null
            ? [
                'from' => $currentTask['from'] ?? null,
                'to' => $currentTask['to'] ?? null,
            ]
            : null,
    ];
}

function pj_history_sync_save_state(array $state): void
{
    $state['updated_at'] = gmdate('c');
    pj_write_json_file(pj_history_sync_state_file(), $state);
}

function pj_history_sync_load_state(): ?array
{
    $state = pj_read_json_file(pj_history_sync_state_file(), null);
    return is_array($state) ? $state : null;
}

function pj_history_sync_initialize_state(): array
{
    if (!pj_has_api_key()) {
        throw new RuntimeException('Configure api.key em config.local.php para sincronizar o historico.');
    }

    $bookmakers = pj_odds_api_bookmakers();
    if ($bookmakers === []) {
        throw new RuntimeException('Configure api.bookmakers em config.local.php para sincronizar o historico.');
    }

    $timezone = (string) (pj_config()['api']['timezone'] ?? 'America/Sao_Paulo');
    $start = pj_history_sync_history_start($timezone);
    $end = pj_history_sync_now($timezone);
    $windows = pj_history_sync_build_windows($start, $end);
    $leagues = pj_history_sync_fetch_leagues();
    $tasks = pj_history_sync_build_tasks($leagues, $windows);
    $existingRows = pj_history_sync_read_source_rows();

    $state = [
        'version' => 1,
        'status' => $tasks === [] ? 'completed' : 'running',
        'started_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
        'tasks' => $tasks,
        'next_task_index' => 0,
        'current_task' => null,
        'pending_events' => [],
        'counts' => pj_history_sync_empty_counts(
            pj_history_sync_count_preserved_rows($existingRows),
            count($existingRows)
        ),
        'warning' => null,
        'message' => $tasks === []
            ? 'Nenhuma liga historica disponivel para sincronizar.'
            : 'Sincronizacao do historico iniciada.',
    ];
    $state['progress'] = pj_history_sync_build_progress($state);

    pj_history_sync_save_state($state);

    return $state;
}

function pj_history_sync_payload(array $state, bool $ok): array
{
    return [
        'ok' => $ok,
        'state' => (string) ($state['status'] ?? 'running'),
        'progress' => is_array($state['progress'] ?? null) ? $state['progress'] : pj_history_sync_build_progress($state),
        'counts' => is_array($state['counts'] ?? null) ? $state['counts'] : pj_history_sync_empty_counts(),
        'warning' => $state['warning'] ?? null,
        'message' => $state['message'] ?? null,
    ];
}

function pj_history_sync_step(bool $reset = false): array
{
    $state = null;

    try {
        $state = $reset ? pj_history_sync_initialize_state() : pj_history_sync_load_state();
        if ($state === null) {
            $state = pj_history_sync_initialize_state();
        }

        if (($state['status'] ?? '') === 'completed') {
            $state['progress'] = pj_history_sync_build_progress($state);
            return pj_history_sync_payload($state, true);
        }

        $bookmakers = pj_odds_api_bookmakers();
        $timezone = (string) (pj_config()['api']['timezone'] ?? 'America/Sao_Paulo');
        $tasks = is_array($state['tasks'] ?? null) ? $state['tasks'] : [];
        $batchRows = [];
        $processedEvents = 0;
        $maxEvents = pj_history_sync_max_events_per_step();

        while ($processedEvents < $maxEvents) {
            $pendingEvents = is_array($state['pending_events'] ?? null) ? array_values($state['pending_events']) : [];
            if ($pendingEvents === []) {
                $nextTaskIndex = max(0, (int) ($state['next_task_index'] ?? 0));
                if ($nextTaskIndex >= count($tasks)) {
                    $state['status'] = 'completed';
                    $state['current_task'] = null;
                    $state['message'] = 'Sincronizacao do historico concluida.';
                    break;
                }

                $task = is_array($tasks[$nextTaskIndex] ?? null) ? $tasks[$nextTaskIndex] : null;
                if ($task === null) {
                    $state['next_task_index'] = $nextTaskIndex + 1;
                    continue;
                }

                $state['current_task'] = $task;
                $pendingEvents = pj_history_sync_fetch_events_for_task($task);
                if ($pendingEvents === []) {
                    $state['pending_events'] = [];
                    $state['next_task_index'] = $nextTaskIndex + 1;
                    $state['current_task'] = null;
                    continue;
                }

                $state['pending_events'] = $pendingEvents;
            }

            $pendingEvents = is_array($state['pending_events'] ?? null) ? array_values($state['pending_events']) : [];
            if ($pendingEvents === []) {
                continue;
            }

            $event = array_shift($pendingEvents);
            $state['pending_events'] = $pendingEvents;
            $processedEvents++;

            $oddsEntry = pj_history_sync_fetch_odds_for_event((int) ($event['id'] ?? 0), $bookmakers);
            $rows = pj_history_sync_rows_from_event($event, $oddsEntry, $timezone);
            if ($rows === []) {
                $state['counts']['skipped'] = (int) ($state['counts']['skipped'] ?? 0) + 1;
            } else {
                array_push($batchRows, ...$rows);
            }

            if ($pendingEvents === []) {
                $state['next_task_index'] = max(0, (int) ($state['next_task_index'] ?? 0)) + 1;
                $state['current_task'] = null;
            }
        }

        if ($batchRows !== []) {
            pj_history_sync_merge_rows($batchRows, $state['counts']);
        }

        if (($state['status'] ?? '') !== 'completed' && max(0, (int) ($state['next_task_index'] ?? 0)) >= count($tasks)) {
            $pendingEvents = is_array($state['pending_events'] ?? null) ? $state['pending_events'] : [];
            if ($pendingEvents === []) {
                $state['status'] = 'completed';
                $state['current_task'] = null;
                $state['message'] = 'Sincronizacao do historico concluida.';
            }
        }

        if (($state['status'] ?? '') !== 'completed') {
            $state['status'] = 'running';
            $state['message'] = 'Sincronizacao do historico em andamento.';
        }

        $state['warning'] = null;
        $state['progress'] = pj_history_sync_build_progress($state);
        pj_history_sync_save_state($state);

        return pj_history_sync_payload($state, true);
    } catch (Throwable $error) {
        $warning = pj_format_api_fetch_warning($error, pj_odds_api_bookmakers());
        $state = is_array($state) ? $state : [
            'status' => 'error',
            'tasks' => [],
            'next_task_index' => 0,
            'current_task' => null,
            'pending_events' => [],
            'counts' => pj_history_sync_empty_counts(),
        ];
        $state['status'] = 'error';
        $state['warning'] = $warning;
        $state['message'] = 'Falha na sincronizacao do historico.';
        $state['progress'] = pj_history_sync_build_progress($state);
        pj_history_sync_save_state($state);

        return pj_history_sync_payload($state, false);
    }
}