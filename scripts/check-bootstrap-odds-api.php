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

$GLOBALS['__PJ_CONFIG_OVERRIDE'] = [
    'api' => [
        'bookmakers' => ['Bet365', 'Betano', '1xbet'],
    ],
];

$event = [
    'id' => 68048762,
    'home' => 'Adelaide United FC',
    'away' => 'FK Beograd',
    'date' => '2026-03-06T05:30:00Z',
    'status' => 'live',
    'league' => [
        'name' => 'Australia - South Australia NPL',
        'slug' => 'australia-south-australia-npl',
    ],
    'sport' => [
        'name' => 'Football',
        'slug' => 'football',
    ],
    'scores' => [
        'home' => 2,
        'away' => 1,
    ],
];

$oddsWithHt = [
    'id' => 68048762,
    'home' => 'Adelaide United FC',
    'away' => 'FK Beograd',
    'date' => '2026-03-06T05:30:00Z',
    'status' => 'live',
    'bookmakers' => [
        'Bet365' => [
            [
                'name' => 'ML',
                'updatedAt' => '2026-03-06T04:59:30.008Z',
                'odds' => [
                    [
                        'home' => '2.500',
                        'draw' => '3.800',
                        'away' => '2.200',
                    ],
                ],
            ],
            [
                'name' => 'Half Time Result',
                'updatedAt' => '2026-03-06T04:59:30.008Z',
                'odds' => [
                    [
                        'label' => '1',
                        'under' => '2.875',
                    ],
                    [
                        'label' => 'Draw',
                        'under' => '2.600',
                    ],
                    [
                        'label' => '2',
                        'under' => '2.625',
                    ],
                ],
            ],
        ],
    ],
];

$rows = pj_build_rows_from_odds_event($event, $oddsWithHt, 'America/Sao_Paulo');
assert_same(2, count($rows), 'Should generate one row per side when ML exists.');
assert_same('2.5', $rows[0]['odd_ft'], 'Should normalize FT home odd from ML.');
assert_same('2.875', $rows[0]['odd_ht'], 'Should normalize HT home odd from Half Time Result.');
assert_same('2x1', $rows[0]['score_ht'], 'Should expose the current score.');
assert_same('LIVE', $rows[0]['match_status'], 'Should normalize live status.');
assert_same('Bet365', $rows[0]['bookmaker'], 'Should preserve the selected bookmaker name.');
assert_same('', (string) ($rows[0]['elapsed_min'] ?? ''), 'Elapsed minutes should stay null/empty for Odds API rows.');

$oddsWithoutHt = [
    'id' => 68048762,
    'home' => 'Adelaide United FC',
    'away' => 'FK Beograd',
    'date' => '2026-03-06T05:30:00Z',
    'status' => 'pending',
    'bookmakers' => [
        'Bet365' => [
            [
                'name' => 'ML',
                'updatedAt' => '2026-03-06T04:59:30.008Z',
                'odds' => [
                    [
                        'home' => '2.500',
                        'draw' => '3.800',
                        'away' => '2.200',
                    ],
                ],
            ],
        ],
    ],
];

$pendingEvent = $event;
$pendingEvent['status'] = 'pending';
$pendingEvent['scores'] = ['home' => 0, 'away' => 0];
$rowsWithoutHt = pj_build_rows_from_odds_event($pendingEvent, $oddsWithoutHt, 'America/Sao_Paulo');
assert_same('', $rowsWithoutHt[0]['odd_ht'], 'Should keep HT odd empty when the market is missing.');
assert_same('', $rowsWithoutHt[0]['score_ht'], 'Should not show a fake 0x0 score for pending matches.');
assert_same('NS', $rowsWithoutHt[0]['match_status'], 'Should normalize pending status to NS.');

$fallbackOdds = [
    'id' => 68048762,
    'home' => 'Adelaide United FC',
    'away' => 'FK Beograd',
    'date' => '2026-03-06T05:30:00Z',
    'status' => 'live',
    'bookmakers' => [
        'SingBet' => [
            [
                'name' => 'ML',
                'odds' => [
                    [
                        'home' => '2.600',
                        'draw' => '3.700',
                        'away' => '2.150',
                    ],
                ],
            ],
        ],
    ],
];

$selection = pj_select_bookmaker($fallbackOdds, 'Adelaide United FC', 'FK Beograd');
assert_same('SingBet', $selection['bookmaker'], 'Should fall back to any bookmaker with compatible ML market.');
assert_true(is_float($selection['ft']['home'] ?? null), 'Fallback bookmaker should still provide FT odds.');

assert_same(
    'Missing bookmakers',
    pj_upstream_error_message(['error' => 'Missing bookmakers']),
    'Should read Odds API top-level error messages.'
);

echo "Bootstrap Odds API checks passed.\n";
