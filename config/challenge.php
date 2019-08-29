<?php

use Acme\ChallengeType\Types;

return [
    // Space/comma-separated list of default authorization ports.
    'default_authorization_ports' => '80',
    'types' => [
        'http_intercept' => [
            'class' => Types\HttpInterceptChallenge::class,
        ],
        'http_physical' => [
            'class' => Types\HttpPhysicalChallenge::class,
        ],
    ],
];
