<?php

return [
    'production' => [
        'letsencrypt' => [
            'name' => 'LetsEncrypt',
            'directoryUrl' => 'https://acme-v02.api.letsencrypt.org/directory',
            'authorizationPorts' => [80, 443],
            'allowUnsafeConnections' => false,
        ],
        'letsencrypt_v1' => [
            'name' => 'LetsEncrypt (V1)',
            'directoryUrl' => 'https://acme-v01.api.letsencrypt.org/directory',
            'authorizationPorts' => [80, 443],
            'allowUnsafeConnections' => false,
        ],
    ],
    'staging' => [
        'letsencrypt_staging' => [
            'name' => 'LetsEncrypt STAGING',
            'directoryUrl' => 'https://acme-staging-v02.api.letsencrypt.org/directory',
            'authorizationPorts' => [80, 443],
            'allowUnsafeConnections' => false,
        ],
        'letsencrypt_staging_v1' => [
            'name' => 'LetsEncrypt STAGING (V1)',
            'directoryUrl' => 'https://acme-staging.api.letsencrypt.org/directory',
            'authorizationPorts' => [80, 443],
            'allowUnsafeConnections' => false,
        ],
    ],
    'test' => [
        'boulder' => [
            'name' => 'Boulder',
            'directoryUrl' => 'https://boulder:4431/directory',
            'authorizationPorts' => [5002],
            'allowUnsafeConnections' => true,
        ],
        'boulder_v1' => [
            'name' => 'Boulder (V1)',
            'directoryUrl' => 'https://boulder:4430/directory',
            'authorizationPorts' => [5002],
            'allowUnsafeConnections' => true,
        ],
        'pebble' => [
            'name' => 'Pebble',
            'directoryUrl' => 'https://localhost:14000/dir',
            'authorizationPorts' => [5002],
            'allowUnsafeConnections' => true,
        ],
    ],
];
