<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = strtolower(trim((string) ($_GET['action'] ?? '')));

if ($method === 'GET' && $action === 'session') {
    pj_json_response([
        'ok' => true,
        'authenticated' => pj_is_authenticated(),
        'username' => pj_auth_username(),
    ]);
}

if ($method === 'POST' && $action === 'login') {
    $payload = pj_request_body();
    $config = pj_config();
    $expectedUser = trim((string) ($config['auth']['username'] ?? ''));
    $expectedHash = trim((string) ($config['auth']['password_hash'] ?? ''));
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($username === '' || $password === '') {
        pj_json_response([
            'ok' => false,
            'error' => 'Usuario e senha sao obrigatorios.',
        ], 422);
    }

    if ($username !== $expectedUser || $expectedHash === '' || !password_verify($password, $expectedHash)) {
        pj_json_response([
            'ok' => false,
            'error' => 'Credenciais invalidas.',
        ], 401);
    }

    pj_login($username);
    pj_json_response([
        'ok' => true,
        'authenticated' => true,
        'username' => $username,
    ]);
}

if ($method === 'POST' && $action === 'logout') {
    pj_logout();
    pj_json_response([
        'ok' => true,
        'authenticated' => false,
    ]);
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
