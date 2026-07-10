#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/auth.php';

assertTrue(
    authenticate(
        'mikrotik-test',
        'mikrotik.v1.test-token',
        $HOST_TOKENS
    )
);

assertTrue(
    authenticate(
        'bredland-test',
        'bredland.v1.test-token',
        $HOST_TOKENS
    )
);

assertFalse(
    authenticate(
        'mikrotik-test',
        'wrong-token',
        $HOST_TOKENS
    )
);

assertFalse(
    authenticate(
        'unknown-host',
        'anything',
        $HOST_TOKENS
    )
);
