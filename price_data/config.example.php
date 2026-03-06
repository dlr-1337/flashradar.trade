<?php

declare(strict_types=1);

return [
    'auth' => [
        'username' => 'admin',
        'password_hash' => '$2y$12$4kmOKSDz8KNkcK9XaRGCWO8U2UX56.iwOXcZf5nBGzUBFuQO2j1A2',
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
