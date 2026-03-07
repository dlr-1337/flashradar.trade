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
    pj_require_auth_json();
    $payload = pj_request_body();
    $reset = (bool) ($payload['reset'] ?? false);
    $response = pj_history_sync_step($reset);
    pj_json_response($response, ($response['ok'] ?? false) ? 200 : 500);
}

if ($method === 'GET') {
    $apiRows = pj_fetch_api_rows();
    $jsonRows = pj_json_source_rows();
    $manualRows = pj_manual_rows();
    $allRows = pj_sort_dashboard_rows(array_merge($apiRows['rows'], $jsonRows['rows'], $manualRows));
    $warning = pj_merge_warnings([
        $apiRows['meta']['warning'] ?? null,
        $jsonRows['warning'] ?? null,
    ]);

    pj_json_response([
        'ok' => true,
        'rows' => $allRows,
        'meta' => array_merge($apiRows['meta'], [
            'warning' => $warning,
            'refresh_seconds' => (int) (pj_config()['dashboard']['refresh_seconds'] ?? 60),
            'authenticated' => pj_is_authenticated(),
        ]),
    ]);
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