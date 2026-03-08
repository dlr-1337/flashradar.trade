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

function build_api_code(string $apiFile, array $fileOverrides, array $configOverride, string $method, string $action = '', ?array $body = null, ?array $session = null): string
{
    $lines = [
        'if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }',
        '$GLOBALS[' . var_export('__PJ_FILE_OVERRIDES', true) . '] = ' . var_export($fileOverrides, true) . ';',
        '$GLOBALS[' . var_export('__PJ_CONFIG_OVERRIDE', true) . '] = ' . var_export($configOverride, true) . ';',
        '$_SERVER[' . var_export('REQUEST_METHOD', true) . '] = ' . var_export($method, true) . ';',
    ];

    if ($session !== null) {
        $lines[] = '$_SESSION[' . var_export('pricejust_user', true) . '] = ' . var_export($session, true) . ';';
    }

    if ($action !== '') {
        $lines[] = '$_GET[' . var_export('action', true) . '] = ' . var_export($action, true) . ';';
    }

    if ($body !== null) {
        $lines[] = '$GLOBALS[' . var_export('__PJ_REQUEST_BODY', true) . '] = ' . var_export($body, true) . ';';
    }

    $lines[] = 'include ' . var_export($apiFile, true) . ';';

    return implode("\n", $lines);
}

function build_session(array $user): array
{
    return [
        'id' => (string) ($user['id'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'role' => (string) ($user['role'] ?? 'user'),
    ];
}

$repoRoot = dirname(__DIR__);
$apiFile = str_replace('\\', '/', $repoRoot . '/price_data/api.php');
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flashradar-api-auth-' . bin2hex(random_bytes(6));

if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
    throw new RuntimeException('Unable to create temp directory for auth checks.');
}

$usersFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'users.json');
$manualFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'manual.json');
$cacheFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'dashboard.json');
$jsonFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'dados.json');

file_put_contents($manualFile, "[]\n");
file_put_contents($jsonFile, "[]\n");

$configOverride = [
    'auth' => [
        'username' => 'admin',
        'password_hash' => password_hash('change-me', PASSWORD_DEFAULT),
    ],
    'api' => [
        'key' => '',
        'timezone' => 'America/Sao_Paulo',
    ],
    'dashboard' => [
        'refresh_seconds' => 60,
    ],
];

$fileOverrides = [
    'users' => $usersFile,
    'manual' => $manualFile,
    'cache' => $cacheFile,
    'json_source' => $jsonFile,
];

$sessionPayload = run_php_json(build_api_code($apiFile, $fileOverrides, $configOverride, 'GET', 'session'));
assert_same(true, $sessionPayload['ok'] ?? false, 'Session endpoint should respond with ok=true.');
assert_same(false, $sessionPayload['authenticated'] ?? null, 'Unauthenticated session should return authenticated=false.');
assert_same(false, $sessionPayload['capabilities']['can_manage_users'] ?? null, 'Unauthenticated session should not expose user management capability.');

$listPayload = run_php_json(build_api_code($apiFile, $fileOverrides, $configOverride, 'GET'));
assert_same(true, $listPayload['ok'] ?? false, 'Listing rows should still work without authentication.');
assert_true(is_array($listPayload['rows'] ?? null), 'Listing rows should return a rows array.');
assert_same('local', $listPayload['meta']['mode'] ?? null, 'Default listing should stay in local mode.');
assert_same(false, $listPayload['meta']['date_filter']['applied'] ?? null, 'Default listing should not apply a date filter.');

$loginPayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'login',
    ['username' => 'admin', 'password' => 'change-me']
));
assert_same(true, $loginPayload['ok'] ?? false, 'Admin login should succeed with the seeded credentials.');
assert_same('admin', $loginPayload['role'] ?? null, 'Admin login should return role=admin.');
assert_same(true, $loginPayload['capabilities']['can_manage_users'] ?? null, 'Admin login should expose user management capability.');

$storedUsers = json_decode((string) file_get_contents($usersFile), true, 512, JSON_THROW_ON_ERROR);
assert_same(1, count($storedUsers), 'Auth seed should create the first admin user.');
assert_same('admin', $storedUsers[0]['role'] ?? null, 'Seeded user should be admin.');
$adminSession = build_session($storedUsers[0]);

$usersPayload = run_php_json(build_api_code($apiFile, $fileOverrides, $configOverride, 'GET', 'users', null, $adminSession));
assert_same(true, $usersPayload['ok'] ?? false, 'Admin should be able to list users.');
assert_same(1, count($usersPayload['users'] ?? []), 'Initial user list should contain only the admin.');

$createPayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'create_user',
    ['username' => 'operador', 'password' => 'trader123'],
    $adminSession
));
assert_same(true, $createPayload['ok'] ?? false, 'Admin should be able to create a normal user.');
assert_same('user', $createPayload['user']['role'] ?? null, 'Created user should always be a normal user.');
assert_same(true, $createPayload['user']['active'] ?? null, 'Created user should start active.');
$createdUserId = (string) ($createPayload['user']['id'] ?? '');
assert_true($createdUserId !== '', 'Created user should expose an id.');

$updatePayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'update_user',
    ['id' => $createdUserId, 'username' => 'operador2'],
    $adminSession
));
assert_same(true, $updatePayload['ok'] ?? false, 'Admin should be able to update a normal user login.');
assert_same('operador2', $updatePayload['user']['username'] ?? null, 'Updated username should be persisted.');

$storedUsers = json_decode((string) file_get_contents($usersFile), true, 512, JSON_THROW_ON_ERROR);
assert_same(2, count($storedUsers), 'User store should contain admin plus the created user.');
$userRecord = null;
foreach ($storedUsers as $candidate) {
    if (($candidate['id'] ?? '') === $createdUserId) {
        $userRecord = $candidate;
        break;
    }
}
assert_true(is_array($userRecord), 'Created user must be persisted to the user store.');
$userSession = build_session($userRecord);

$userSessionPayload = run_php_json(build_api_code($apiFile, $fileOverrides, $configOverride, 'GET', 'session', null, $userSession));
assert_same(true, $userSessionPayload['authenticated'] ?? false, 'User session should authenticate successfully.');
assert_same('user', $userSessionPayload['role'] ?? null, 'User session should report role=user.');
assert_same(false, $userSessionPayload['capabilities']['can_manage_users'] ?? null, 'Normal users must not manage other logins.');

$blockedUsersPayload = run_php_json(build_api_code($apiFile, $fileOverrides, $configOverride, 'GET', 'users', null, $userSession));
assert_same(false, $blockedUsersPayload['ok'] ?? true, 'Normal users should be blocked from the admin user list endpoint.');
assert_same('ADMIN_REQUIRED', $blockedUsersPayload['error'] ?? null, 'Blocked admin endpoint should return ADMIN_REQUIRED.');

$changePasswordPayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'change_password',
    ['current_password' => 'trader123', 'new_password' => 'trader456'],
    $userSession
));
assert_same(true, $changePasswordPayload['ok'] ?? false, 'Normal users should be able to change their own password.');

$oldLoginPayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'login',
    ['username' => 'operador2', 'password' => 'trader123']
));
assert_same(false, $oldLoginPayload['ok'] ?? true, 'Old password should stop working after password change.');

$newLoginPayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'login',
    ['username' => 'operador2', 'password' => 'trader456']
));
assert_same(true, $newLoginPayload['ok'] ?? false, 'User should be able to log in with the new password.');
assert_same('user', $newLoginPayload['role'] ?? null, 'New login should keep role=user.');

$resetPayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'reset_user_password',
    ['id' => $createdUserId, 'password' => 'reset1234'],
    $adminSession
));
assert_same(true, $resetPayload['ok'] ?? false, 'Admin should be able to reset a user password.');

$postResetOldLogin = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'login',
    ['username' => 'operador2', 'password' => 'trader456']
));
assert_same(false, $postResetOldLogin['ok'] ?? true, 'Previous password should stop working after admin reset.');

$postResetLogin = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'login',
    ['username' => 'operador2', 'password' => 'reset1234']
));
assert_same(true, $postResetLogin['ok'] ?? false, 'Reset password should allow login again.');

$togglePayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'toggle_user_active',
    ['id' => $createdUserId, 'active' => false],
    $adminSession
));
assert_same(true, $togglePayload['ok'] ?? false, 'Admin should be able to deactivate a normal user.');
assert_same(false, $togglePayload['user']['active'] ?? true, 'Deactivated user should return active=false.');

$inactiveLoginPayload = run_php_json(build_api_code(
    $apiFile,
    $fileOverrides,
    $configOverride,
    'POST',
    'login',
    ['username' => 'operador2', 'password' => 'reset1234']
));
assert_same(false, $inactiveLoginPayload['ok'] ?? true, 'Inactive users should not be able to log in.');

$usersPayload = run_php_json(build_api_code($apiFile, $fileOverrides, $configOverride, 'GET', 'users', null, $adminSession));
assert_same(2, count($usersPayload['users'] ?? []), 'Admin user list should include both the admin and the managed user.');

echo "API auth checks passed.\n";
