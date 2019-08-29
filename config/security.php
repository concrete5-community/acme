<?php

return [
    'key_size' => [
        // The minimum allowed (in bits) of the private keys to be generated
        'min' => 1024,
        // The default size (in bits) of the private keys to be generated
        'default' => 2048,
    ],
    'openssl' => [
        // The absolute path to the OpenSSL configuration file
        // If not set, or if does not exist, it'll create it at runtime
        'config_file' => '',
    ],
];
