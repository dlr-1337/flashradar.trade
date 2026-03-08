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

$guardOk = pj_api_run_guarded(static fn (): array => ['ok' => true]);
assert_same(true, $guardOk['ok'] ?? false, 'Guard should keep successful closures as ok=true.');
assert_same('', $guardOk['captured_output'] ?? null, 'Guard should keep captured output empty for clean success.');

$guardRuntime = pj_api_run_guarded(static function (): array {
    throw new RuntimeException('runtime exploded');
});
assert_same(false, $guardRuntime['ok'] ?? true, 'Guard should report runtime exceptions as ok=false.');
assert_true($guardRuntime['error'] instanceof RuntimeException, 'Guard should expose RuntimeException instances.');
assert_same('runtime exploded', $guardRuntime['error']->getMessage(), 'Guard should preserve runtime exception messages.');

$guardWarning = pj_api_run_guarded(static function (): array {
    trigger_error('warning exploded', E_USER_WARNING);
    return ['ok' => true];
});
assert_same(false, $guardWarning['ok'] ?? true, 'Guard should convert warnings into caught failures.');
assert_true($guardWarning['error'] instanceof ErrorException, 'Guard should convert warnings to ErrorException.');
assert_same('warning exploded', $guardWarning['error']->getMessage(), 'Guard should preserve warning text.');

$guardOutput = pj_api_run_guarded(static function (): array {
    echo "DEBUG OUTPUT\n";
    return ['ok' => true];
});
assert_same(true, $guardOutput['ok'] ?? false, 'Guard should keep successful closures with output as ok=true.');
assert_contains('DEBUG OUTPUT', (string) ($guardOutput['captured_output'] ?? ''), 'Guard should capture buffered output.');

$repoRoot = dirname(__DIR__);
$apiFile = str_replace('\\', '/', $repoRoot . '/price_data/api.php');
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flashradar-api-dashboard-guard-' . bin2hex(random_bytes(6));

if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
    throw new RuntimeException('Unable to create temp directory for dashboard guard checks.');
}

$logFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'api-errors.log');

$localOutputCode = strtr(<<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'GET';
$GLOBALS['__PJ_FILE_OVERRIDES'] = [
    'api_error_log' => __LOG_FILE__,
];
$GLOBALS['__PJ_API_DASHBOARD_EXECUTOR_OVERRIDE'] = static function (string $mode, string $dateFrom, string $dateTo): array {
    echo "debug-output-before-json";
    return [
        'ok' => true,
        'rows' => [],
        'meta' => [
            'stale' => false,
            'configured' => true,
            'from_cache' => false,
            'fetched_at' => null,
            'warning' => null,
            'mode' => 'local',
            'date_filter' => [
                'applied' => false,
                'from' => null,
                'to' => null,
            ],
            'refresh_seconds' => 60,
            'authenticated' => false,
        ],
    ];
};
include __API_FILE__;
PHP, [
    '__API_FILE__' => var_export($apiFile, true),
    '__LOG_FILE__' => var_export($logFile, true),
]);

$localOutputPayload = run_php_json($localOutputCode);
assert_same(true, $localOutputPayload['ok'] ?? false, 'Dashboard GET should stay JSON-serializable when output is emitted.');
assert_same(true, $localOutputPayload['meta']['stale'] ?? false, 'Discarded output should mark the response as stale.');
assert_contains(
    'Saida inesperada do PHP foi descartada.',
    (string) ($localOutputPayload['meta']['warning'] ?? ''),
    'Discarded output should be surfaced as a warning.'
);

$loggedOutputIssue = (string) file_get_contents($logFile);
assert_contains('unexpected_output', $loggedOutputIssue, 'Discarded output should be logged locally.');
assert_contains('debug-output-before-json', $loggedOutputIssue, 'The log should keep the discarded output prefix.');

$historyLogFile = str_replace('\\', '/', $tempDir . DIRECTORY_SEPARATOR . 'history-api-errors.log');
$historyErrorCode = strtr(<<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['mode'] = 'history';
$_GET['date_from'] = '2025-01-01';
$_GET['date_to'] = '2025-01-31';
$GLOBALS['__PJ_FILE_OVERRIDES'] = [
    'api_error_log' => __LOG_FILE__,
];
$GLOBALS['__PJ_API_DASHBOARD_EXECUTOR_OVERRIDE'] = static function (string $mode, string $dateFrom, string $dateTo): array {
    throw new RuntimeException('history exploded');
};
include __API_FILE__;
PHP, [
    '__API_FILE__' => var_export($apiFile, true),
    '__LOG_FILE__' => var_export($historyLogFile, true),
]);

$historyErrorPayload = run_php_json($historyErrorCode);
assert_same(false, $historyErrorPayload['ok'] ?? true, 'Unexpected history failures should still return JSON.');
assert_contains(
    'Falha ao buscar historico.',
    (string) ($historyErrorPayload['error'] ?? ''),
    'Unexpected history failures should return a history-specific error prefix.'
);
assert_contains(
    'history exploded',
    (string) ($historyErrorPayload['error'] ?? ''),
    'Unexpected history failures should preserve the technical detail.'
);
assert_contains(
    'price_data/storage/cache/api-errors.log',
    (string) ($historyErrorPayload['error'] ?? ''),
    'Unexpected history failures should point the user to the local log.'
);

$loggedHistoryIssue = (string) file_get_contents($historyLogFile);
assert_contains('history exploded', $loggedHistoryIssue, 'Unexpected history failures should be logged locally.');
assert_contains('"mode":"history"', $loggedHistoryIssue, 'History failures should log the mode context.');

echo "Dashboard guard checks passed.\n";
