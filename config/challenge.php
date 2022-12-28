<?php

use Acme\ChallengeType\Types;

return [
    // Space/comma-separated list of default authorization ports.
    'default_authorization_ports' => '80 443',
    'types' => [
        'http_intercept' => [
            'class' => Types\HttpInterceptChallenge::class,
        ],
        'http_physical' => [
            'class' => Types\HttpPhysicalChallenge::class,
        ],
        'dns_challtestsrv' => [
            'class' => Types\DnsChallTestSrvChallenge::class,
            'default_management_address' => 'http://localhost:8055',
        ],
        'dns_digitalocean' => [
            'class' => Types\DigitalOceanDnsChallenge::class,
        ],
    ],
];
