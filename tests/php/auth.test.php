#!/usr/bin/env php
<?php
require getenv('TEST_CONFIG');
require __DIR__ . '/lib/testlib.php';
$repoRoot = dirname(dirname(__DIR__));
require $repoRoot . '/templates/noc/lib/compatibility.php';
require $repoRoot . '/templates/noc/lib/auth.php';

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
