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

function assert_throws(callable $callback, string $expectedNeedle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        if ($expectedNeedle !== '' && !str_contains($error->getMessage(), $expectedNeedle)) {
            throw new RuntimeException(
                $message
                . ' Unexpected exception message: ' . $error->getMessage()
            );
        }

        return;
    }

    throw new RuntimeException($message . ' Expected exception was not thrown.');
}

$requestLimitMessage = 'You have reached the request limit for the day';

assert_same(
    $requestLimitMessage,
    pj_upstream_error_message([
        'errors' => [
            'requests' => $requestLimitMessage,
        ],
    ]),
    'Should extract the upstream error text from the payload.'
);

assert_same(
    null,
    pj_upstream_error_message([
        'errors' => [],
        'response' => [],
    ]),
    'Should ignore empty upstream errors.'
);

assert_throws(
    static function () use ($requestLimitMessage): void {
        pj_assert_upstream_payload([
            'errors' => [
                'requests' => $requestLimitMessage,
            ],
            'response' => [],
        ], 200);
    },
    $requestLimitMessage,
    'Should reject HTTP 200 responses that still contain upstream API errors.'
);

pj_assert_upstream_payload([
    'errors' => [],
    'response' => [],
], 200);

echo "Bootstrap upstream error checks passed.\n";
