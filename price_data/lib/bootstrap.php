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

function pj_file_override_path(string $key, string $default): string
{
    $overrides = $GLOBALS['__PJ_FILE_OVERRIDES'] ?? null;
    if (is_array($overrides)) {
        $candidate = $overrides[$key] ?? null;
        if (is_string($candidate) && trim($candidate) !== '') {
            return $candidate;
        }
    }

    return $default;
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

function pj_collect_output_buffers(int $targetLevel): string
{
    $chunks = [];
    while (ob_get_level() > $targetLevel) {
        $chunk = ob_get_clean();
        if (is_string($chunk) && $chunk !== '') {
            array_unshift($chunks, $chunk);
        }
    }

    return implode('', $chunks);
}

function pj_api_run_guarded(callable $work): array
{
    $targetLevel = ob_get_level();
    ob_start();
    set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    $result = [
        'ok' => false,
        'payload' => null,
        'error' => null,
        'captured_output' => '',
    ];

    try {
        $payload = $work();
        if (!is_array($payload)) {
            throw new RuntimeException('Guarded API callback must return an array payload.');
        }

        $result['ok'] = true;
        $result['payload'] = $payload;
    } catch (Throwable $error) {
        $result['error'] = $error;
    } finally {
        restore_error_handler();
        $result['captured_output'] = pj_collect_output_buffers($targetLevel);
    }

    return $result;
}

function pj_api_error_log_file(): string
{
    return pj_file_override_path('api_error_log', pj_storage_path('cache/api-errors.log'));
}

function pj_append_api_error_log(array $entry): void
{
    $file = pj_api_error_log_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $payload = $entry;
    $payload['timestamp'] = trim((string) ($payload['timestamp'] ?? '')) ?: gmdate('c');
    $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        $line = json_encode([
            'timestamp' => gmdate('c'),
            'message' => 'Failed to encode API error log entry.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    file_put_contents($file, (string) $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function pj_upstream_error_message(array $payload): ?string
{
    $candidates = [];
    if (array_key_exists('errors', $payload)) {
        $candidates[] = $payload['errors'];
    }
    if (array_key_exists('error', $payload)) {
        $candidates[] = $payload['error'];
    }
    if (array_key_exists('message', $payload)) {
        $candidates[] = $payload['message'];
    }

    if ($candidates === []) {
        return null;
    }

    $messages = [];

    $collect = static function (mixed $value) use (&$messages): void {
        if (!is_scalar($value)) {
            return;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return;
        }

        $messages[] = $text;
    };

    foreach ($candidates as $candidate) {
        if (is_array($candidate)) {
            array_walk_recursive($candidate, static function (mixed $value) use ($collect): void {
                $collect($value);
            });
            continue;
        }

        $collect($candidate);
    }

    if ($messages === []) {
        return null;
    }

    return implode(' ', array_values(array_unique($messages)));
}

function pj_assert_upstream_payload(array $payload, int $status): void
{
    $upstreamError = pj_upstream_error_message($payload);
    if ($upstreamError !== null) {
        throw new RuntimeException('Upstream API error: ' . $upstreamError);
    }

    if ($status >= 400) {
        throw new RuntimeException('Upstream returned HTTP ' . $status);
    }
}

function pj_load_config_file(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $loaded = require $file;

    return is_array($loaded) ? $loaded : [];
}

function pj_normalize_bookmaker_names(mixed $bookmakers): array
{
    $normalized = [];
    foreach ((array) $bookmakers as $bookmaker) {
        $name = pj_canonical_bookmaker_name(trim((string) $bookmaker));
        if ($name === '') {
            continue;
        }

        $normalized[] = $name;
    }

    return array_values(array_unique($normalized));
}

function pj_canonical_bookmaker_name(string $name): string
{
    $trimmed = trim($name);
    if ($trimmed === '') {
        return '';
    }

    $collapsed = preg_replace('/[^a-z0-9]+/', '', strtolower($trimmed));
    if (!is_string($collapsed) || $collapsed === '') {
        return $trimmed;
    }

    if ($collapsed === 'bet365') {
        return 'Bet365';
    }

    if ($collapsed === 'betfairexchange' || levenshtein($collapsed, 'betfairexchange') <= 2) {
        return 'Betfair Exchange';
    }

    return $trimmed;
}

function pj_format_configured_bookmakers(array $bookmakers): string
{
    $normalized = pj_normalize_bookmaker_names($bookmakers);
    if ($normalized === []) {
        return '[]';
    }

    return '[' . implode(', ', $normalized) . ']';
}

function pj_format_api_fetch_warning(Throwable $error, array $bookmakers): string
{
    $message = trim($error->getMessage());
    if ($message === '') {
        return 'Falha ao consultar a Odds API.';
    }

    $normalized = strtolower($message);
    $isBookmakerAccessIssue = str_contains($normalized, 'access denied')
        && str_contains($normalized, 'bookmaker');

    if ($isBookmakerAccessIssue) {
        return 'Falha ao consultar a Odds API com api.bookmakers em price_data/config.local.php = '
            . pj_format_configured_bookmakers($bookmakers)
            . '. Ajuste essa lista para casas permitidas pela sua conta. Detalhe do upstream: '
            . $message;
    }

    return $message;
}

function pj_config(): array
{
    static $baseConfig;

    if ($baseConfig === null) {
        $default = [
            'auth' => [
                'username' => 'admin',
                'password_hash' => '',
            ],
            'api' => [
                'key' => '',
                'base_url' => 'https://api.odds-api.io/v3',
                'sport' => 'football',
                'timezone' => 'America/Sao_Paulo',
                'cache_ttl_seconds' => 60,
                'bookmakers' => ['Bet365', 'Betano', '1xbet'],
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
        $baseConfig = array_replace_recursive($default, $example, $local);

        $baseUrl = trim((string) ($baseConfig['api']['base_url'] ?? ''));
        if ($baseUrl === '' || str_contains(strtolower($baseUrl), 'api-sports')) {
            $baseConfig['api']['base_url'] = $default['api']['base_url'];
        }

        $sport = trim((string) ($baseConfig['api']['sport'] ?? ''));
        if ($sport === '') {
            $baseConfig['api']['sport'] = $default['api']['sport'];
        }

        $bookmakers = $local['api']['bookmakers'] ?? $local['api']['bookmaker_priority'] ?? $example['api']['bookmakers'] ?? $example['api']['bookmaker_priority'] ?? $default['api']['bookmakers'];
        $baseConfig['api']['bookmakers'] = pj_normalize_bookmaker_names($bookmakers);
    }

    $override = $GLOBALS['__PJ_CONFIG_OVERRIDE'] ?? null;
    if (is_array($override) && $override !== []) {
        $resolved = array_replace_recursive($baseConfig, $override);
        $overrideBookmakers = $override['api']['bookmakers'] ?? $override['api']['bookmaker_priority'] ?? null;
        if ($overrideBookmakers !== null) {
            $resolved['api']['bookmakers'] = pj_normalize_bookmaker_names($overrideBookmakers);
        }

        return $resolved;
    }

    return $baseConfig;
}

function pj_users_file(): string
{
    return pj_file_override_path('users', pj_storage_path('users.json'));
}

function pj_now_iso8601(): string
{
    $override = $GLOBALS['__PJ_NOW_ISO'] ?? null;
    if (is_string($override) && trim($override) !== '') {
        return trim($override);
    }

    return gmdate('c');
}

function pj_normalize_username(string $username): string
{
    $trimmed = trim($username);
    if ($trimmed === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($trimmed, 'UTF-8');
    }

    return strtolower($trimmed);
}

function pj_validate_username(string $username): string
{
    $trimmed = trim($username);
    if ($trimmed === '') {
        throw new InvalidArgumentException('Usuario e obrigatorio.');
    }

    if (strlen($trimmed) < 3) {
        throw new InvalidArgumentException('Usuario deve ter pelo menos 3 caracteres.');
    }

    if (preg_match('/^[A-Za-z0-9._-]+$/', $trimmed) !== 1) {
        throw new InvalidArgumentException('Usuario deve usar apenas letras, numeros, ponto, underline ou hifen.');
    }

    return $trimmed;
}

function pj_validate_password(string $password): string
{
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Senha deve ter pelo menos 8 caracteres.');
    }

    return $password;
}

function pj_next_user_id(): string
{
    return 'user_' . bin2hex(random_bytes(8));
}

function pj_normalize_user_record(array $user): array
{
    $id = trim((string) ($user['id'] ?? ''));
    if ($id === '') {
        $id = pj_next_user_id();
    }

    $username = trim((string) ($user['username'] ?? ''));
    $passwordHash = trim((string) ($user['password_hash'] ?? ''));
    $role = trim((string) ($user['role'] ?? 'user'));
    $active = (bool) ($user['active'] ?? true);
    $createdAt = trim((string) ($user['created_at'] ?? ''));
    $updatedAt = trim((string) ($user['updated_at'] ?? ''));
    $now = pj_now_iso8601();

    return [
        'id' => $id,
        'username' => $username,
        'password_hash' => $passwordHash,
        'role' => $role === 'admin' ? 'admin' : 'user',
        'active' => $active,
        'created_at' => $createdAt !== '' ? $createdAt : $now,
        'updated_at' => $updatedAt !== '' ? $updatedAt : $now,
    ];
}

function pj_sort_users(array $users): array
{
    usort($users, static function (array $a, array $b): int {
        $adminOrder = ((string) ($a['role'] ?? 'user') === 'admin' ? 0 : 1)
            <=> ((string) ($b['role'] ?? 'user') === 'admin' ? 0 : 1);
        if ($adminOrder !== 0) {
            return $adminOrder;
        }

        return strcmp(
            pj_normalize_username((string) ($a['username'] ?? '')),
            pj_normalize_username((string) ($b['username'] ?? ''))
        );
    });

    return array_values($users);
}

function pj_seed_admin_user_from_config(): array
{
    $config = pj_config();
    $username = trim((string) ($config['auth']['username'] ?? 'admin'));
    $passwordHash = trim((string) ($config['auth']['password_hash'] ?? ''));

    if ($username === '') {
        $username = 'admin';
    }

    if ($passwordHash === '') {
        $passwordHash = password_hash('change-me', PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new RuntimeException('Unable to seed admin password hash.');
        }
    }

    $now = pj_now_iso8601();

    return [
        'id' => 'admin_general',
        'username' => $username,
        'password_hash' => $passwordHash,
        'role' => 'admin',
        'active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

function pj_user_store(): array
{
    $rows = pj_read_json_file(pj_users_file(), []);
    $users = [];

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = pj_normalize_user_record($row);
            if ($normalized['username'] === '' || $normalized['password_hash'] === '') {
                continue;
            }

            $users[] = $normalized;
        }
    }

    if ($users === []) {
        $users = [pj_seed_admin_user_from_config()];
        pj_write_json_file(pj_users_file(), $users);
    }

    return pj_sort_users($users);
}

function pj_save_user_store(array $users): array
{
    $normalized = [];
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }

        $record = pj_normalize_user_record($user);
        if ($record['username'] === '' || $record['password_hash'] === '') {
            continue;
        }

        $normalized[] = $record;
    }

    $normalized = pj_sort_users($normalized);
    pj_write_json_file(pj_users_file(), $normalized);

    return $normalized;
}

function pj_find_user_index_by_id(array $users, string $id): ?int
{
    foreach ($users as $index => $user) {
        if ((string) ($user['id'] ?? '') === $id) {
            return $index;
        }
    }

    return null;
}

function pj_find_user_by_id(string $id): ?array
{
    if ($id === '') {
        return null;
    }

    foreach (pj_user_store() as $user) {
        if ((string) ($user['id'] ?? '') === $id) {
            return $user;
        }
    }

    return null;
}

function pj_find_user_by_username(string $username): ?array
{
    $normalized = pj_normalize_username($username);
    if ($normalized === '') {
        return null;
    }

    foreach (pj_user_store() as $user) {
        if (pj_normalize_username((string) ($user['username'] ?? '')) === $normalized) {
            return $user;
        }
    }

    return null;
}

function pj_count_active_admins(array $users): int
{
    $count = 0;
    foreach ($users as $user) {
        if ((string) ($user['role'] ?? 'user') !== 'admin') {
            continue;
        }

        if ((bool) ($user['active'] ?? false) !== true) {
            continue;
        }

        $count++;
    }

    return $count;
}

function pj_public_user(array $user): array
{
    return [
        'id' => (string) ($user['id'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'role' => (string) ($user['role'] ?? 'user'),
        'active' => (bool) ($user['active'] ?? false),
        'created_at' => (string) ($user['created_at'] ?? ''),
        'updated_at' => (string) ($user['updated_at'] ?? ''),
    ];
}

function pj_list_public_users(): array
{
    return array_map(
        static fn (array $user): array => pj_public_user($user),
        pj_user_store()
    );
}

function pj_session_user_payload(array $user): array
{
    return [
        'id' => (string) ($user['id'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'role' => (string) ($user['role'] ?? 'user'),
    ];
}

function pj_clear_auth_session(): void
{
    unset($_SESSION[PRICEJUST_SESSION_KEY]);
}

function pj_current_user(): ?array
{
    $sessionUser = $_SESSION[PRICEJUST_SESSION_KEY] ?? null;
    if ($sessionUser === null) {
        return null;
    }

    $resolved = null;

    if (is_array($sessionUser)) {
        $sessionId = trim((string) ($sessionUser['id'] ?? ''));
        if ($sessionId !== '') {
            $resolved = pj_find_user_by_id($sessionId);
        }

        if ($resolved === null) {
            $sessionUsername = trim((string) ($sessionUser['username'] ?? ''));
            if ($sessionUsername !== '') {
                $resolved = pj_find_user_by_username($sessionUsername);
            }
        }
    } elseif (is_string($sessionUser) && $sessionUser !== '') {
        $resolved = pj_find_user_by_username($sessionUser);
    }

    if ($resolved === null || (bool) ($resolved['active'] ?? false) !== true) {
        pj_clear_auth_session();
        return null;
    }

    $expectedPayload = pj_session_user_payload($resolved);
    if ($sessionUser !== $expectedPayload) {
        $_SESSION[PRICEJUST_SESSION_KEY] = $expectedPayload;
    }

    return $resolved;
}

function pj_is_authenticated(): bool
{
    return pj_current_user() !== null;
}

function pj_auth_username(): ?string
{
    $user = pj_current_user();
    return is_array($user) ? (string) ($user['username'] ?? '') : null;
}

function pj_auth_user_id(): ?string
{
    $user = pj_current_user();
    return is_array($user) ? (string) ($user['id'] ?? '') : null;
}

function pj_auth_role(): ?string
{
    $user = pj_current_user();
    return is_array($user) ? (string) ($user['role'] ?? '') : null;
}

function pj_auth_capabilities(?array $user = null): array
{
    $resolved = $user ?? pj_current_user();
    $isAdmin = is_array($resolved) && (string) ($resolved['role'] ?? '') === 'admin';

    return [
        'can_manage_users' => $isAdmin,
        'can_change_password' => $resolved !== null,
        'can_use_dashboard' => $resolved !== null,
        'can_edit_manual_rows' => $resolved !== null,
    ];
}

function pj_is_admin(): bool
{
    return pj_auth_role() === 'admin';
}

function pj_login_user(array $user): void
{
    $_SESSION[PRICEJUST_SESSION_KEY] = pj_session_user_payload($user);
    session_regenerate_id(true);
}

function pj_login(string $username): void
{
    $user = pj_find_user_by_username($username);
    if ($user === null || (bool) ($user['active'] ?? false) !== true) {
        throw new RuntimeException('Unable to log in unknown user.');
    }

    pj_login_user($user);
}

function pj_logout(): void
{
    pj_clear_auth_session();
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

function pj_require_admin_json(): void
{
    pj_require_auth_json();

    if (!pj_is_admin()) {
        pj_json_response([
            'ok' => false,
            'error' => 'ADMIN_REQUIRED',
        ], 403);
    }
}

function pj_require_admin_page(): void
{
    pj_require_auth_page();

    if (!pj_is_admin()) {
        header('Location: index.php');
        exit;
    }
}

function pj_find_saved_user_by_id(array $users, string $id): array
{
    foreach ($users as $user) {
        if ((string) ($user['id'] ?? '') === $id) {
            return $user;
        }
    }

    throw new RuntimeException('Nao foi possivel localizar o usuario salvo.');
}

function pj_create_user(string $username, string $password): array
{
    $validatedUsername = pj_validate_username($username);
    $validatedPassword = pj_validate_password($password);
    $users = pj_user_store();

    foreach ($users as $candidate) {
        if (pj_normalize_username((string) ($candidate['username'] ?? '')) === pj_normalize_username($validatedUsername)) {
            throw new InvalidArgumentException('Ja existe um usuario com esse login.');
        }
    }

    $now = pj_now_iso8601();
    $passwordHash = password_hash($validatedPassword, PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Nao foi possivel gerar a senha do usuario.');
    }

    $users[] = [
        'id' => pj_next_user_id(),
        'username' => $validatedUsername,
        'password_hash' => $passwordHash,
        'role' => 'user',
        'active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $savedUsers = pj_save_user_store($users);
    $createdUser = pj_find_user_by_username($validatedUsername);
    if ($createdUser === null) {
        throw new RuntimeException('Nao foi possivel salvar o novo usuario.');
    }

    return pj_public_user($createdUser);
}

function pj_update_user_username(string $id, string $username): array
{
    $validatedUsername = pj_validate_username($username);
    $users = pj_user_store();
    $userIndex = pj_find_user_index_by_id($users, trim($id));

    if ($userIndex === null) {
        throw new InvalidArgumentException('Usuario nao encontrado.');
    }

    $currentUser = $users[$userIndex];
    if ((string) ($currentUser['role'] ?? 'user') === 'admin') {
        throw new InvalidArgumentException('O admin geral nao pode ser editado por esta tela.');
    }

    foreach ($users as $candidate) {
        if ((string) ($candidate['id'] ?? '') === (string) ($currentUser['id'] ?? '')) {
            continue;
        }

        if (pj_normalize_username((string) ($candidate['username'] ?? '')) === pj_normalize_username($validatedUsername)) {
            throw new InvalidArgumentException('Ja existe um usuario com esse login.');
        }
    }

    $users[$userIndex]['username'] = $validatedUsername;
    $users[$userIndex]['updated_at'] = pj_now_iso8601();
    $savedUsers = pj_save_user_store($users);

    return pj_public_user(pj_find_saved_user_by_id($savedUsers, (string) ($currentUser['id'] ?? '')));
}

function pj_reset_user_password(string $id, string $password): array
{
    $validatedPassword = pj_validate_password($password);
    $users = pj_user_store();
    $userIndex = pj_find_user_index_by_id($users, trim($id));

    if ($userIndex === null) {
        throw new InvalidArgumentException('Usuario nao encontrado.');
    }

    $currentUser = $users[$userIndex];
    if ((string) ($currentUser['role'] ?? 'user') === 'admin') {
        throw new InvalidArgumentException('O admin geral nao pode ser alterado por esta tela.');
    }

    $passwordHash = password_hash($validatedPassword, PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Nao foi possivel atualizar a senha.');
    }

    $users[$userIndex]['password_hash'] = $passwordHash;
    $users[$userIndex]['updated_at'] = pj_now_iso8601();
    $savedUsers = pj_save_user_store($users);

    return pj_public_user(pj_find_saved_user_by_id($savedUsers, (string) ($currentUser['id'] ?? '')));
}

function pj_toggle_user_active(string $id, bool $active): array
{
    $users = pj_user_store();
    $userIndex = pj_find_user_index_by_id($users, trim($id));

    if ($userIndex === null) {
        throw new InvalidArgumentException('Usuario nao encontrado.');
    }

    $targetUser = $users[$userIndex];
    if ((string) ($targetUser['role'] ?? 'user') === 'admin' && $active === false && pj_count_active_admins($users) <= 1) {
        throw new InvalidArgumentException('Nao e permitido desativar o unico admin geral.');
    }

    if ((string) ($targetUser['role'] ?? 'user') === 'admin') {
        throw new InvalidArgumentException('O admin geral nao pode ser alterado por esta tela.');
    }

    $users[$userIndex]['active'] = $active;
    $users[$userIndex]['updated_at'] = pj_now_iso8601();
    $savedUsers = pj_save_user_store($users);

    return pj_public_user(pj_find_saved_user_by_id($savedUsers, (string) ($targetUser['id'] ?? '')));
}

function pj_change_password(string $userId, string $currentPassword, string $newPassword): array
{
    if (trim($userId) === '') {
        throw new InvalidArgumentException('Sessao invalida.');
    }

    $validatedPassword = pj_validate_password($newPassword);
    $users = pj_user_store();
    $userIndex = pj_find_user_index_by_id($users, trim($userId));

    if ($userIndex === null) {
        throw new InvalidArgumentException('Usuario nao encontrado.');
    }

    $currentUser = $users[$userIndex];
    if (!password_verify($currentPassword, (string) ($currentUser['password_hash'] ?? ''))) {
        throw new InvalidArgumentException('Senha atual invalida.');
    }

    $passwordHash = password_hash($validatedPassword, PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Nao foi possivel atualizar a senha.');
    }

    $users[$userIndex]['password_hash'] = $passwordHash;
    $users[$userIndex]['updated_at'] = pj_now_iso8601();
    $savedUsers = pj_save_user_store($users);
    $updatedUser = pj_find_saved_user_by_id($savedUsers, (string) ($currentUser['id'] ?? ''));

    if (pj_auth_user_id() === (string) ($updatedUser['id'] ?? '')) {
        $_SESSION[PRICEJUST_SESSION_KEY] = pj_session_user_payload($updatedUser);
    }

    return pj_public_user($updatedUser);
}

function pj_manual_file(): string
{
    return pj_file_override_path('manual', pj_storage_path('manual.json'));
}

function pj_cache_file(): string
{
    return pj_file_override_path('cache', pj_storage_path('cache/dashboard.json'));
}

function pj_json_source_file(): string
{
    return pj_file_override_path('json_source', pj_repo_path('dados.json'));
}

function pj_history_sync_state_file(): string
{
    return pj_file_override_path('history_sync_state', pj_storage_path('cache/history-sync.json'));
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

function pj_json_source_rows(): array
{
    $file = pj_json_source_file();
    if (!is_file($file)) {
        return [
            'rows' => [],
            'warning' => 'Arquivo dados.json nao encontrado na raiz do projeto.',
        ];
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        return [
            'rows' => [],
            'warning' => 'Nao foi possivel ler o arquivo dados.json.',
        ];
    }

    if (trim($raw) === '') {
        return [
            'rows' => [],
            'warning' => 'Arquivo dados.json esta vazio.',
        ];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'rows' => [],
            'warning' => 'Arquivo dados.json invalido: ' . json_last_error_msg() . '.',
        ];
    }

    if (!is_array($decoded)) {
        return [
            'rows' => [],
            'warning' => 'Arquivo dados.json deve conter uma lista de registros.',
        ];
    }

    if ($decoded === []) {
        return [
            'rows' => [],
            'warning' => 'Arquivo dados.json nao contem registros.',
        ];
    }

    $rows = [];
    foreach ($decoded as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $rows[] = pj_normalize_json_source_row($row, (int) $index);
    }

    if ($rows === [] && $decoded !== []) {
        return [
            'rows' => [],
            'warning' => 'Arquivo dados.json nao contem registros validos.',
        ];
    }

    return [
        'rows' => $rows,
        'warning' => null,
    ];
}

function pj_normalize_json_source_row(array $row, int $index): array
{
    $rawId = trim((string) ($row['id'] ?? ''));
    if ($rawId === '') {
        $signature = implode('|', [
            (string) $index,
            trim((string) ($row['league'] ?? '')),
            trim((string) ($row['category'] ?? '')),
            trim((string) ($row['team'] ?? '')),
            trim((string) ($row['venue'] ?? '')),
            trim((string) ($row['date'] ?? '')),
            trim((string) ($row['score_ht'] ?? '')),
            pj_string_number_or_empty($row['odd_ht'] ?? ''),
            pj_string_number_or_empty($row['odd_ft'] ?? ''),
        ]);
        $rawId = substr(sha1($signature), 0, 20);
    }

    $category = trim((string) ($row['category'] ?? ''));
    $oddFt = pj_string_number_or_empty($row['odd_ft'] ?? '');
    if ($category === '' && $oddFt !== '' && is_numeric($oddFt)) {
        $category = pj_category_for_odd((float) $oddFt);
    }

    $fixtureId = $row['fixture_id'] ?? null;
    if ($fixtureId !== null && !is_scalar($fixtureId)) {
        $fixtureId = null;
    }

    $elapsedMin = $row['elapsed_min'] ?? null;
    if ($elapsedMin !== null && !is_numeric($elapsedMin)) {
        $elapsedMin = null;
    }

    $kickoffAt = trim((string) ($row['kickoff_at'] ?? ''));
    $bookmaker = trim((string) ($row['bookmaker'] ?? ''));
    $matchStatus = trim((string) ($row['match_status'] ?? 'FILE'));

    return [
        'id' => 'json_' . $rawId,
        'league' => trim((string) ($row['league'] ?? '')),
        'category' => $category,
        'team' => trim((string) ($row['team'] ?? '')),
        'venue' => trim((string) ($row['venue'] ?? '')),
        'date' => trim((string) ($row['date'] ?? '')),
        'odd_ht' => pj_string_number_or_empty($row['odd_ht'] ?? ''),
        'score_ht' => trim((string) ($row['score_ht'] ?? '')),
        'odd_ft' => $oddFt,
        'opponent' => trim((string) ($row['opponent'] ?? '')),
        'source' => 'json',
        'fixture_id' => $fixtureId,
        'match_status' => $matchStatus !== '' ? $matchStatus : 'FILE',
        'elapsed_min' => $elapsedMin !== null ? (float) $elapsedMin : null,
        'kickoff_at' => $kickoffAt !== '' ? $kickoffAt : null,
        'bookmaker' => $bookmaker !== '' ? $bookmaker : null,
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

function pj_merge_warnings(array $warnings): ?string
{
    $normalized = [];
    foreach ($warnings as $warning) {
        $text = trim((string) $warning);
        if ($text === '') {
            continue;
        }

        $normalized[] = $text;
    }

    if ($normalized === []) {
        return null;
    }

    return implode(' ', array_values(array_unique($normalized)));
}

function pj_dashboard_date_filter_payload(?string $from = null, ?string $to = null): array
{
    $start = is_string($from) ? trim($from) : '';
    $end = is_string($to) ? trim($to) : '';
    $applied = $start !== '' && $end !== '';

    return [
        'applied' => $applied,
        'from' => $applied ? $start : null,
        'to' => $applied ? $end : null,
    ];
}

function pj_resolve_dashboard_date_filter(?string $from = null, ?string $to = null, ?string $timezone = null): array
{
    $resolvedTimezone = $timezone ?: (string) (pj_config()['api']['timezone'] ?? 'America/Sao_Paulo');
    $start = is_string($from) ? trim($from) : '';
    $end = is_string($to) ? trim($to) : '';

    if ($start === '' && $end === '') {
        return [
            'applied' => false,
            'from' => null,
            'to' => null,
            'start' => null,
            'end' => null,
            'timezone' => $resolvedTimezone,
        ];
    }

    if ($start === '' || $end === '') {
        throw new InvalidArgumentException('date_from e date_to sao obrigatorios.');
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
        throw new InvalidArgumentException('date_from e date_to devem usar o formato YYYY-MM-DD.');
    }

    $tz = new DateTimeZone($resolvedTimezone);
    $startDate = new DateTimeImmutable($start . ' 00:00:00', $tz);
    $endDate = new DateTimeImmutable($end . ' 23:59:59', $tz);

    if ($endDate < $startDate) {
        throw new InvalidArgumentException('date_to deve ser igual ou posterior a date_from.');
    }

    return [
        'applied' => true,
        'from' => $start,
        'to' => $end,
        'start' => $startDate,
        'end' => $endDate,
        'timezone' => $resolvedTimezone,
    ];
}

function pj_row_matches_date_filter(array $row, array $dateFilter): bool
{
    if (($dateFilter['applied'] ?? false) !== true) {
        return true;
    }

    $timezone = new DateTimeZone((string) ($dateFilter['timezone'] ?? pj_config()['api']['timezone'] ?? 'America/Sao_Paulo'));
    $start = $dateFilter['start'] ?? null;
    $end = $dateFilter['end'] ?? null;
    if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
        return true;
    }

    $kickoffAt = trim((string) ($row['kickoff_at'] ?? ''));
    if ($kickoffAt !== '') {
        try {
            $kickoff = (new DateTimeImmutable($kickoffAt))->setTimezone($timezone);
            return $kickoff >= $start && $kickoff <= $end;
        } catch (Throwable) {
            return false;
        }
    }

    $dateLabel = trim((string) ($row['date'] ?? ''));
    if ($dateLabel === '') {
        return false;
    }

    if (preg_match('/^\d{4}-\d{2}$/', $dateLabel) === 1) {
        try {
            $monthStart = new DateTimeImmutable($dateLabel . '-01 00:00:00', $timezone);
            $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
            return $monthEnd >= $start && $monthStart <= $end;
        } catch (Throwable) {
            return false;
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateLabel) === 1) {
        try {
            $rowDate = new DateTimeImmutable($dateLabel . ' 12:00:00', $timezone);
            return $rowDate >= $start && $rowDate <= $end;
        } catch (Throwable) {
            return false;
        }
    }

    return false;
}

function pj_filter_dashboard_rows_by_date_filter(array $rows, array $dateFilter): array
{
    if (($dateFilter['applied'] ?? false) !== true) {
        return array_values($rows);
    }

    $filtered = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (pj_row_matches_date_filter($row, $dateFilter)) {
            $filtered[] = $row;
        }
    }

    return array_values($filtered);
}

function pj_collect_local_dashboard_rows(?array $dateFilter = null): array
{
    $resolvedFilter = is_array($dateFilter) ? $dateFilter : pj_resolve_dashboard_date_filter();
    $jsonRows = pj_json_source_rows();
    $rows = array_merge($jsonRows['rows'], pj_manual_rows());

    return [
        'rows' => pj_filter_dashboard_rows_by_date_filter($rows, $resolvedFilter),
        'warning' => $jsonRows['warning'] ?? null,
    ];
}

function pj_dashboard_row_merge_key(array $row): string
{
    $fixtureId = (int) ($row['fixture_id'] ?? 0);
    $venue = trim((string) ($row['venue'] ?? ''));
    if ($fixtureId > 0 && $venue !== '') {
        return 'fixture:' . $fixtureId . '|' . strtolower($venue);
    }

    $rowId = trim((string) ($row['id'] ?? ''));
    if ($rowId !== '') {
        return 'id:' . $rowId;
    }

    return 'hash:' . sha1(json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
}

function pj_merge_dashboard_row_sets(array ...$rowSets): array
{
    $merged = [];
    foreach ($rowSets as $rows) {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $merged[pj_dashboard_row_merge_key($row)] = $row;
        }
    }

    return array_values($merged);
}

function pj_sort_dashboard_rows(array $rows): array
{
    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) ($a['league'] ?? ''), (string) ($b['league'] ?? ''))
            ?: strcmp((string) ($a['category'] ?? ''), (string) ($b['category'] ?? ''))
            ?: strcmp((string) ($a['team'] ?? ''), (string) ($b['team'] ?? ''))
            ?: strcmp((string) ($a['venue'] ?? ''), (string) ($b['venue'] ?? ''))
            ?: strcmp((string) ($a['source'] ?? ''), (string) ($b['source'] ?? ''))
            ?: strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    return array_values($rows);
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

    return $key !== ''
        && $key !== 'PASTE_API_FOOTBALL_KEY_HERE'
        && $key !== 'PASTE_ODDS_API_KEY_HERE';
}

function pj_http_json(string $method, string $url, array $headers = []): array
{
    $override = $GLOBALS['__PJ_HTTP_JSON_OVERRIDE'] ?? null;
    if (is_callable($override)) {
        $result = $override($method, $url, $headers);
        if (!is_array($result)) {
            throw new RuntimeException('HTTP override must return an array payload.');
        }

        return $result;
    }

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

    pj_assert_upstream_payload($decoded, $status);

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

function pj_odds_api_normalize_status(mixed $status): string
{
    $normalized = strtolower(trim((string) $status));
    return match ($normalized) {
        'live', 'inplay', 'in_play', 'in-play', 'running' => 'LIVE',
        'halftime', 'half-time', 'ht' => 'HT',
        'ended', 'completed', 'settled', 'final', 'finished' => 'FT',
        'postponed', 'canceled', 'cancelled', 'suspended', 'abandoned' => 'PST',
        'pending', 'upcoming', 'scheduled', 'not_started', 'not-started' => 'NS',
        default => strtoupper(trim((string) $status)),
    };
}

function pj_odds_api_score_label(array $event): string
{
    $status = pj_odds_api_normalize_status($event['status'] ?? '');
    if (in_array($status, ['NS', 'PST', 'TBD'], true)) {
        return '';
    }

    $home = $event['scores']['home'] ?? null;
    $away = $event['scores']['away'] ?? null;
    if (!is_numeric((string) $home) || !is_numeric((string) $away)) {
        return '';
    }

    return (string) ((int) $home) . 'x' . (string) ((int) $away);
}

function pj_odds_api_event_date_iso(array $event): ?string
{
    $date = $event['date'] ?? null;
    return is_string($date) && trim($date) !== '' ? $date : null;
}

function pj_odds_api_bookmakers(): array
{
    $bookmakers = pj_config()['api']['bookmakers'] ?? [];
    $normalized = [];
    foreach ((array) $bookmakers as $bookmaker) {
        $name = trim((string) $bookmaker);
        if ($name === '') {
            continue;
        }

        $normalized[] = $name;
    }

    return array_values(array_unique($normalized));
}

function pj_odds_api_headers(): array
{
    return [
        'Accept' => 'application/json',
    ];
}

function pj_odds_api_url(string $path, array $params = []): string
{
    $baseUrl = rtrim((string) (pj_config()['api']['base_url'] ?? ''), '/');
    $query = [];
    foreach ($params as $key => $value) {
        if ($value === null) {
            continue;
        }

        if (is_string($value) && trim($value) === '') {
            continue;
        }

        $query[$key] = $value;
    }

    return $baseUrl . $path . ($query !== [] ? '?' . http_build_query($query) : '');
}

function pj_odds_api_get(string $path, array $params = []): array
{
    $params['apiKey'] = (string) (pj_config()['api']['key'] ?? '');
    return pj_http_json('GET', pj_odds_api_url($path, $params), pj_odds_api_headers());
}

function pj_find_market(array $bookmaker, array $aliases): ?array
{
    $markets = $bookmaker['markets'] ?? $bookmaker['bets'] ?? $bookmaker['odds'] ?? [];
    foreach ($markets as $bet) {
        if (!is_array($bet)) {
            continue;
        }

        $name = strtolower(trim((string) ($bet['name'] ?? '')));
        foreach ($aliases as $alias) {
            if ($name === strtolower($alias)) {
                return $bet;
            }
        }
    }

    return null;
}

function pj_odds_api_market_price(array $entry): ?float
{
    foreach (['odd', 'price', 'decimal', 'under', 'over'] as $key) {
        $value = $entry[$key] ?? null;
        if ($value !== null && is_numeric((string) $value)) {
            return (float) $value;
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

    foreach ((array) ($market['odds'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $directValue = $entry[$side] ?? null;
        if ($directValue !== null && is_numeric((string) $directValue)) {
            return (float) $directValue;
        }

        $labels = [
            strtolower(trim((string) ($entry['label'] ?? ''))),
            strtolower(trim((string) ($entry['name'] ?? ''))),
            strtolower(trim((string) ($entry['value'] ?? ''))),
        ];

        $matched = false;
        foreach ($aliases as $alias) {
            if (in_array($alias, $labels, true)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            continue;
        }

        $price = pj_odds_api_market_price($entry);
        if ($price !== null) {
            return $price;
        }
    }

    return null;
}

function pj_bookmaker_has_team_markets(array $bookmaker, string $homeName, string $awayName): array
{
    $ftAliases = ['ml', 'moneyline', 'match winner', '1x2', 'winner', 'full time result'];
    $htAliases = ['half time result', 'halftime result', '1st half winner', 'first half result'];

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
    $bookmakers = $oddsEntry['bookmakers'] ?? [];
    if (!is_array($bookmakers)) {
        return [];
    }

    foreach ($bookmakers as $name => $markets) {
        if (is_string($name)) {
            $sourceName = trim($name);
            $sourceMarkets = is_array($markets) ? $markets : [];
        } elseif (is_array($markets)) {
            $sourceName = trim((string) ($markets['name'] ?? ''));
            $sourceMarkets = $markets['markets'] ?? $markets['odds'] ?? [];
            $sourceMarkets = is_array($sourceMarkets) ? $sourceMarkets : [];
        } else {
            continue;
        }

        if ($sourceName === '') {
            continue;
        }

        $sources[] = [
            'name' => $sourceName,
            'markets' => $sourceMarkets,
        ];
    }

    return $sources;
}

function pj_select_bookmaker(array $oddsEntry, string $homeName, string $awayName): array
{
    $priority = pj_odds_api_bookmakers();
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

            if (strcasecmp((string) ($bookmaker['name'] ?? ''), $wanted) === 0) {
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
                'bookmaker' => (string) ($bookmaker['name'] ?? 'Odds API'),
                'ft' => $marketSet['ft'],
                'ht' => $marketSet['ht'],
            ];
        }

        if ($hasFt && $bestFtOnly === null) {
            $bestFtOnly = [
                'bookmaker' => (string) ($bookmaker['name'] ?? 'Odds API'),
                'ft' => $marketSet['ft'],
                'ht' => $marketSet['ht'],
            ];
        }
    }

    return $bestFtOnly ?? [];
}

function pj_build_rows_from_odds_event(array $event, array $oddsEntry, string $timezone): array
{
    $eventId = (int) ($event['id'] ?? $oddsEntry['id'] ?? 0);
    $homeName = trim((string) ($event['home'] ?? $oddsEntry['home'] ?? ''));
    $awayName = trim((string) ($event['away'] ?? $oddsEntry['away'] ?? ''));
    if ($eventId <= 0 || $homeName === '' || $awayName === '') {
        return [];
    }

    $bookmakerSelection = pj_select_bookmaker($oddsEntry, $homeName, $awayName);
    if ($bookmakerSelection === []) {
        return [];
    }

    $homeFt = $bookmakerSelection['ft']['home'] ?? null;
    $awayFt = $bookmakerSelection['ft']['away'] ?? null;
    if (!is_float($homeFt) || !is_float($awayFt)) {
        return [];
    }

    $kickoffAt = pj_odds_api_event_date_iso($event) ?? pj_odds_api_event_date_iso($oddsEntry);
    $dateLabel = pj_format_kickoff_for_table($kickoffAt, $timezone);
    $status = pj_odds_api_normalize_status($event['status'] ?? $oddsEntry['status'] ?? '');
    $scoreLabel = pj_odds_api_score_label($event);
    $league = trim((string) ($event['league']['name'] ?? $oddsEntry['league']['name'] ?? ''));
    $bookmakerName = (string) ($bookmakerSelection['bookmaker'] ?? '');

    return [
        [
            'id' => 'api_' . $eventId . '_home',
            'league' => $league,
            'category' => pj_category_for_odd($homeFt),
            'team' => $homeName,
            'venue' => 'Casa',
            'date' => $dateLabel,
            'odd_ht' => isset($bookmakerSelection['ht']['home']) && is_float($bookmakerSelection['ht']['home']) ? (string) $bookmakerSelection['ht']['home'] : '',
            'score_ht' => $scoreLabel,
            'odd_ft' => (string) $homeFt,
            'opponent' => $awayName,
            'source' => 'api',
            'fixture_id' => $eventId,
            'match_status' => $status,
            'elapsed_min' => null,
            'kickoff_at' => $kickoffAt,
            'bookmaker' => $bookmakerName,
        ],
        [
            'id' => 'api_' . $eventId . '_away',
            'league' => $league,
            'category' => pj_category_for_odd($awayFt),
            'team' => $awayName,
            'venue' => 'Fora',
            'date' => $dateLabel,
            'odd_ht' => isset($bookmakerSelection['ht']['away']) && is_float($bookmakerSelection['ht']['away']) ? (string) $bookmakerSelection['ht']['away'] : '',
            'score_ht' => $scoreLabel,
            'odd_ft' => (string) $awayFt,
            'opponent' => $homeName,
            'source' => 'api',
            'fixture_id' => $eventId,
            'match_status' => $status,
            'elapsed_min' => null,
            'kickoff_at' => $kickoffAt,
            'bookmaker' => $bookmakerName,
        ],
    ];
}

function pj_odds_api_today_window(string $timezone): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
    $end = $now->setTime(23, 59, 59);

    return [
        'from' => $now->format(DateTimeInterface::RFC3339),
        'to' => $end->format(DateTimeInterface::RFC3339),
    ];
}

function pj_odds_api_filter_events(array $payload): array
{
    $events = [];
    foreach ($payload as $event) {
        if (is_array($event)) {
            $events[] = $event;
        }
    }

    return $events;
}

function pj_odds_api_fetch_events(string $timezone): array
{
    $window = pj_odds_api_today_window($timezone);
    $sport = (string) (pj_config()['api']['sport'] ?? 'football');

    $todayEvents = pj_odds_api_filter_events(pj_odds_api_get('/events', [
        'sport' => $sport,
        'from' => $window['from'],
        'to' => $window['to'],
        'status' => 'pending,live',
        'limit' => 250,
    ]));

    $liveEvents = pj_odds_api_filter_events(pj_odds_api_get('/events/live', [
        'sport' => $sport,
    ]));

    $eventsById = [];
    foreach ([$todayEvents, $liveEvents] as $collection) {
        foreach ($collection as $event) {
            $eventId = (int) ($event['id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $eventsById[$eventId] = $event;
        }
    }

    return $eventsById;
}

function pj_odds_api_fetch_multi_odds(array $eventIds, array $bookmakers): array
{
    $oddsByEvent = [];

    foreach (array_chunk(array_values($eventIds), 10) as $chunk) {
        $payload = pj_odds_api_get('/odds/multi', [
            'eventIds' => implode(',', $chunk),
            'bookmakers' => implode(',', $bookmakers),
        ]);

        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $eventId = (int) ($entry['id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $oddsByEvent[$eventId] = $entry;
        }
    }

    return $oddsByEvent;
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
                'from_cache' => false,
                'fetched_at' => null,
                'warning' => 'Modo manual ativo. Configure api.key em config.local.php para carregar odds da Odds API.',
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
                'from_cache' => true,
                'fetched_at' => $cache['fetched_at'] ?? null,
                'warning' => null,
            ],
        ];
    }

    $timezone = (string) ($config['api']['timezone'] ?? 'America/Sao_Paulo');
    $bookmakers = pj_odds_api_bookmakers();

    try {
        if ($bookmakers === []) {
            throw new RuntimeException('Configure api.bookmakers em config.local.php para carregar odds da API.');
        }

        $eventsById = pj_odds_api_fetch_events($timezone);
        $oddsByEvent = $eventsById === [] ? [] : pj_odds_api_fetch_multi_odds(array_keys($eventsById), $bookmakers);

        $rows = [];
        foreach ($eventsById as $eventId => $event) {
            $eventRows = pj_build_rows_from_odds_event($event, $oddsByEvent[$eventId] ?? [], $timezone);
            if ($eventRows !== []) {
                array_push($rows, ...$eventRows);
            }
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
                'from_cache' => false,
                'fetched_at' => $snapshot['fetched_at'],
                'warning' => null,
            ],
        ];
    } catch (Throwable $error) {
        $warning = pj_format_api_fetch_warning($error, $bookmakers);

        if (is_array($cache['rows'] ?? null) && $cache['rows'] !== []) {
            return [
                'rows' => $cache['rows'],
                'meta' => [
                    'stale' => true,
                    'configured' => true,
                    'from_cache' => true,
                    'fetched_at' => $cache['fetched_at'] ?? null,
                    'warning' => $warning,
                ],
            ];
        }

        return [
            'rows' => [],
            'meta' => [
                'stale' => true,
                'configured' => true,
                'from_cache' => false,
                'fetched_at' => null,
                'warning' => $warning,
            ],
        ];
    }
}

require_once __DIR__ . '/history_sync.php';
