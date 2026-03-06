<?php

declare(strict_types=1);

return [
    'auth' => [
        'username' => 'admin',
        'password_hash' => '$2y$12$4kmOKSDz8KNkcK9XaRGCWO8U2UX56.iwOXcZf5nBGzUBFuQO2j1A2',
    ],
    'api' => [
        'key' => 'PASTE_ODDS_API_KEY_HERE',
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
