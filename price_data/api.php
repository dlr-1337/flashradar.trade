<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

function pj_api_user_error_status(Throwable $error): int
{
    $message = $error->getMessage();

    if (str_contains($message, 'Ja existe um usuario')) {
        return 409;
    }

    if (str_contains($message, 'nao encontrado')) {
        return 404;
    }

    if (str_contains($message, 'admin geral') || str_contains($message, 'unico admin')) {
        return 403;
    }

    return 422;
}

function pj_api_dashboard_payload(
    string $mode,
    array $dateFilter,
    array $localRows,
    ?array $historyRows = null,
    ?array $historySync = null,
    ?string $extraWarning = null,
    bool $stale = false
): array {
    $normalizedMode = strtolower(trim($mode)) === 'history' ? 'history' : 'local';
    $resolvedHistoryRows = $normalizedMode === 'history' && is_array($historyRows)
        ? $historyRows
        : ['rows' => [], 'warning' => null];
    $resolvedHistorySync = is_array($historySync)
        ? $historySync
        : pj_history_sync_dashboard_summary($normalizedMode === 'history' ? $dateFilter : null);

    $rows = $normalizedMode === 'history'
        ? pj_merge_dashboard_row_sets(
            is_array($localRows['rows'] ?? null) ? $localRows['rows'] : [],
            is_array($resolvedHistoryRows['rows'] ?? null) ? $resolvedHistoryRows['rows'] : []
        )
        : (is_array($localRows['rows'] ?? null) ? $localRows['rows'] : []);

    $warning = pj_merge_warnings([
        $localRows['warning'] ?? null,
        $resolvedHistoryRows['warning'] ?? null,
        $resolvedHistorySync['warning'] ?? null,
        $extraWarning,
    ]);

    return [
        'ok' => true,
        'rows' => pj_sort_dashboard_rows($rows),
        'meta' => [
            'stale' => $stale,
            'configured' => pj_has_api_key(),
            'from_cache' => false,
            'fetched_at' => $normalizedMode === 'history' ? gmdate('c') : null,
            'warning' => $warning,
            'mode' => $normalizedMode,
            'date_filter' => pj_dashboard_date_filter_payload(
                is_string($dateFilter['from'] ?? null) ? (string) $dateFilter['from'] : null,
                is_string($dateFilter['to'] ?? null) ? (string) $dateFilter['to'] : null
            ),
            'history_sync' => $resolvedHistorySync,
            'refresh_seconds' => (int) (pj_config()['dashboard']['refresh_seconds'] ?? 60),
            'authenticated' => pj_is_authenticated(),
        ],
    ];
}

function pj_api_dashboard_log_issue(array $context, ?Throwable $error = null, string $capturedOutput = ''): void
{
    $entry = [
        'mode' => strtolower(trim((string) ($context['mode'] ?? 'local'))),
        'uri' => trim((string) ($context['uri'] ?? '')),
        'date_from' => trim((string) ($context['date_from'] ?? '')),
        'date_to' => trim((string) ($context['date_to'] ?? '')),
        'issue' => $error instanceof Throwable ? 'exception' : 'unexpected_output',
        'message' => $error instanceof Throwable
            ? trim($error->getMessage())
            : 'Unexpected output discarded while building dashboard response.',
        'error_class' => $error instanceof Throwable ? get_class($error) : null,
        'error_file' => $error instanceof Throwable ? $error->getFile() : null,
        'error_line' => $error instanceof Throwable ? $error->getLine() : null,
        'output_prefix' => $capturedOutput !== '' ? substr($capturedOutput, 0, 600) : null,
    ];

    pj_append_api_error_log(array_filter(
        $entry,
        static fn (mixed $value): bool => $value !== null && $value !== ''
    ));
}

function pj_api_dashboard_unexpected_error_message(string $mode, Throwable $error): string
{
    $prefix = strtolower(trim($mode)) === 'history'
        ? 'Falha ao buscar historico.'
        : 'Falha ao carregar dados locais.';
    $detail = trim($error->getMessage());

    if ($detail === '') {
        return $prefix . ' Consulte price_data/storage/cache/api-errors.log.';
    }

    return $prefix
        . ' Detalhe tecnico: '
        . $detail
        . ' Consulte price_data/storage/cache/api-errors.log.';
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = strtolower(trim((string) ($_GET['action'] ?? '')));
$currentUser = pj_current_user();

if ($method === 'GET' && $action === 'session') {
    pj_json_response([
        'ok' => true,
        'authenticated' => $currentUser !== null,
        'username' => $currentUser['username'] ?? null,
        'role' => $currentUser['role'] ?? null,
        'user' => $currentUser !== null ? pj_public_user($currentUser) : null,
        'capabilities' => pj_auth_capabilities($currentUser),
    ]);
}

if ($method === 'POST' && $action === 'login') {
    $payload = pj_request_body();
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($username === '' || $password === '') {
        pj_json_response([
            'ok' => false,
            'error' => 'Usuario e senha sao obrigatorios.',
        ], 422);
    }

    $user = pj_find_user_by_username($username);
    $passwordHash = is_array($user) ? trim((string) ($user['password_hash'] ?? '')) : '';
    $isActive = is_array($user) ? (bool) ($user['active'] ?? false) : false;

    if ($user === null || $passwordHash === '' || !$isActive || !password_verify($password, $passwordHash)) {
        pj_json_response([
            'ok' => false,
            'error' => 'Credenciais invalidas.',
        ], 401);
    }

    pj_login_user($user);
    pj_json_response([
        'ok' => true,
        'authenticated' => true,
        'username' => $user['username'],
        'role' => $user['role'],
        'user' => pj_public_user($user),
        'capabilities' => pj_auth_capabilities($user),
    ]);
}

if ($method === 'POST' && $action === 'logout') {
    pj_logout();
    pj_json_response([
        'ok' => true,
        'authenticated' => false,
    ]);
}

if ($method === 'GET' && $action === 'users') {
    pj_require_admin_json();
    pj_json_response([
        'ok' => true,
        'users' => pj_list_public_users(),
    ]);
}

if ($method === 'POST' && $action === 'create_user') {
    pj_require_admin_json();
    $payload = pj_request_body();

    try {
        $user = pj_create_user(
            (string) ($payload['username'] ?? ''),
            (string) ($payload['password'] ?? '')
        );
    } catch (Throwable $error) {
        pj_json_response([
            'ok' => false,
            'error' => $error->getMessage(),
        ], pj_api_user_error_status($error));
    }

    pj_json_response([
        'ok' => true,
        'user' => $user,
    ], 201);
}

if ($method === 'POST' && $action === 'update_user') {
    pj_require_admin_json();
    $payload = pj_request_body();

    try {
        $user = pj_update_user_username(
            trim((string) ($payload['id'] ?? '')),
            (string) ($payload['username'] ?? '')
        );
    } catch (Throwable $error) {
        pj_json_response([
            'ok' => false,
            'error' => $error->getMessage(),
        ], pj_api_user_error_status($error));
    }

    pj_json_response([
        'ok' => true,
        'user' => $user,
    ]);
}

if ($method === 'POST' && $action === 'reset_user_password') {
    pj_require_admin_json();
    $payload = pj_request_body();

    try {
        $user = pj_reset_user_password(
            trim((string) ($payload['id'] ?? '')),
            (string) ($payload['password'] ?? '')
        );
    } catch (Throwable $error) {
        pj_json_response([
            'ok' => false,
            'error' => $error->getMessage(),
        ], pj_api_user_error_status($error));
    }

    pj_json_response([
        'ok' => true,
        'user' => $user,
    ]);
}

if ($method === 'POST' && $action === 'toggle_user_active') {
    pj_require_admin_json();
    $payload = pj_request_body();

    if (!array_key_exists('active', $payload)) {
        pj_json_response([
            'ok' => false,
            'error' => 'Campo active e obrigatorio.',
        ], 422);
    }

    $active = filter_var($payload['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (!is_bool($active)) {
        pj_json_response([
            'ok' => false,
            'error' => 'Campo active invalido.',
        ], 422);
    }

    try {
        $user = pj_toggle_user_active(
            trim((string) ($payload['id'] ?? '')),
            $active
        );
    } catch (Throwable $error) {
        pj_json_response([
            'ok' => false,
            'error' => $error->getMessage(),
        ], pj_api_user_error_status($error));
    }

    pj_json_response([
        'ok' => true,
        'user' => $user,
    ]);
}

if ($method === 'POST' && $action === 'change_password') {
    pj_require_auth_json();
    $payload = pj_request_body();

    try {
        $user = pj_change_password(
            (string) (pj_auth_user_id() ?? ''),
            (string) ($payload['current_password'] ?? ''),
            (string) ($payload['new_password'] ?? '')
        );
    } catch (Throwable $error) {
        pj_json_response([
            'ok' => false,
            'error' => $error->getMessage(),
        ], pj_api_user_error_status($error));
    }

    pj_json_response([
        'ok' => true,
        'user' => $user,
        'message' => 'Senha atualizada com sucesso.',
    ]);
}

if ($method === 'POST' && $action === 'sync_history') {
    pj_require_admin_json();
    $payload = pj_request_body();
    $reset = (bool) ($payload['reset'] ?? false);
    $dateFrom = is_string($payload['date_from'] ?? null) ? (string) $payload['date_from'] : null;
    $dateTo = is_string($payload['date_to'] ?? null) ? (string) $payload['date_to'] : null;

    try {
        $response = pj_history_sync_step($reset, $dateFrom, $dateTo);
    } catch (InvalidArgumentException $error) {
        pj_json_response([
            'ok' => false,
            'error' => $error->getMessage(),
        ], 422);
    }

    pj_json_response($response, ($response['ok'] ?? false) ? 200 : 500);
}

if ($method === 'GET') {
    $mode = strtolower(trim((string) ($_GET['mode'] ?? 'local')));
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    $dashboardContext = [
        'mode' => $mode,
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ];
    $resolvedDateFilter = null;
    $localRows = null;

    $guard = pj_api_run_guarded(static function () use ($mode, $dateFrom, $dateTo, &$resolvedDateFilter, &$localRows): array {
        $dashboardExecutorOverride = $GLOBALS['__PJ_API_DASHBOARD_EXECUTOR_OVERRIDE'] ?? null;
        if (is_callable($dashboardExecutorOverride)) {
            return $dashboardExecutorOverride($mode, $dateFrom, $dateTo);
        }

        if ($mode === '' || $mode === 'local') {
            $resolvedDateFilter = pj_resolve_dashboard_date_filter();
            $localRows = pj_collect_local_dashboard_rows($resolvedDateFilter);

            return pj_api_dashboard_payload('local', $resolvedDateFilter, $localRows);
        }

        if ($mode === 'history') {
            $resolvedDateFilter = pj_resolve_dashboard_date_filter($dateFrom, $dateTo);
            $localRows = pj_collect_local_dashboard_rows($resolvedDateFilter);

            return pj_api_dashboard_payload('history', $resolvedDateFilter, $localRows);
        }

        throw new InvalidArgumentException('Modo de listagem invalido.');
    });

    if ($guard['ok'] === true && is_array($guard['payload'] ?? null)) {
        $payload = $guard['payload'];
        $capturedOutput = trim((string) ($guard['captured_output'] ?? ''));
        if ($capturedOutput !== '') {
            pj_api_dashboard_log_issue($dashboardContext, null, $capturedOutput);
            $payload['meta']['stale'] = true;
            $payload['meta']['warning'] = pj_merge_warnings([
                $payload['meta']['warning'] ?? null,
                'Saida inesperada do PHP foi descartada. Consulte price_data/storage/cache/api-errors.log.',
            ]);
        }

        pj_json_response($payload);
    }

    $error = $guard['error'] ?? null;
    if ($error instanceof InvalidArgumentException) {
        pj_json_response([
            'ok' => false,
            'error' => $error->getMessage(),
        ], 422);
    }

    if ($error instanceof Throwable) {
        $capturedOutput = trim((string) ($guard['captured_output'] ?? ''));
        pj_api_dashboard_log_issue($dashboardContext, $error, $capturedOutput);
        $warning = pj_api_dashboard_unexpected_error_message($mode, $error);

        if (is_array($localRows) && is_array($resolvedDateFilter)) {
            if ($mode === 'history') {
                pj_json_response(
                    pj_api_dashboard_payload(
                        'history',
                        $resolvedDateFilter,
                        $localRows,
                        ['rows' => [], 'warning' => null],
                        null,
                        $warning,
                        true
                    )
                );
            }

            pj_json_response(
                pj_api_dashboard_payload(
                    'local',
                    $resolvedDateFilter,
                    $localRows,
                    null,
                    null,
                    $warning,
                    true
                )
            );
        }

        pj_json_response([
            'ok' => false,
            'error' => $warning,
        ], 500);
    }

    pj_json_response([
        'ok' => false,
        'error' => 'Falha ao carregar dados locais. Consulte price_data/storage/cache/api-errors.log.',
    ], 500);
}

pj_require_auth_json();

$manualRows = pj_manual_rows();

if ($method === 'POST') {
    $payload = pj_validate_manual_payload(pj_request_body());
    $manualRows[] = $payload;
    pj_write_json_file(pj_manual_file(), $manualRows);

    pj_json_response([
        'ok' => true,
        'status' => 'sucesso',
        'row' => $payload,
    ], 201);
}

if ($method === 'PUT') {
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') {
        pj_json_response([
            'ok' => false,
            'error' => 'Missing id.',
        ], 422);
    }

    $payload = pj_validate_manual_payload(array_merge(pj_request_body(), ['id' => $id]));
    $updated = false;

    foreach ($manualRows as $index => $row) {
        if ((string) ($row['id'] ?? '') !== $id) {
            continue;
        }

        if (($row['source'] ?? 'manual') !== 'manual') {
            pj_json_response([
                'ok' => false,
                'error' => 'API rows are read-only.',
            ], 403);
        }

        $manualRows[$index] = $payload;
        $updated = true;
        break;
    }

    if (!$updated) {
        pj_json_response([
            'ok' => false,
            'error' => 'Manual row not found.',
        ], 404);
    }

    pj_write_json_file(pj_manual_file(), $manualRows);
    pj_json_response([
        'ok' => true,
        'status' => 'sucesso',
        'row' => $payload,
    ]);
}

if ($method === 'DELETE') {
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') {
        pj_json_response([
            'ok' => false,
            'error' => 'Missing id.',
        ], 422);
    }

    $nextRows = [];
    $removed = false;
    foreach ($manualRows as $row) {
        if ((string) ($row['id'] ?? '') !== $id) {
            $nextRows[] = $row;
            continue;
        }

        if (($row['source'] ?? 'manual') !== 'manual') {
            pj_json_response([
                'ok' => false,
                'error' => 'API rows are read-only.',
            ], 403);
        }

        $removed = true;
    }

    if (!$removed) {
        pj_json_response([
            'ok' => false,
            'error' => 'Manual row not found.',
        ], 404);
    }

    pj_write_json_file(pj_manual_file(), array_values($nextRows));
    pj_json_response([
        'ok' => true,
        'status' => 'sucesso',
    ]);
}

pj_json_response([
    'ok' => false,
    'error' => 'Method not allowed.',
], 405);
