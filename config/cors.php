<?php

declare(strict_types=1);

return [
    'paths' => ['api/track'],

    'allowed_methods' => ['POST', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,
];
