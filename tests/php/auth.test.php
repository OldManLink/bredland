#!/usr/bin/env php
<?php

declare(strict_types=1);

require getenv('TEST_CONFIG');
require __DIR__ . '/lib/testlib.php';
require __DIR__ . '/../../templates/noc/lib/compatibility.php';
require __DIR__ . '/../../templates/noc/lib/auth.php';

assertSame(
    true,
    authenticate(
        'mikrotik-test',
        'mikrotik.v1.test-token',
        $HOST_TOKENS
    )
);

assertSame(
    true,
    authenticate(
        'bredland-test',
        'bredland.v1.test-token',
        $HOST_TOKENS
    )
);

assertSame(
    false,
    authenticate(
        'mikrotik-test',
        'wrong-token',
        $HOST_TOKENS
    )
);

assertSame(
    false,
    authenticate(
        'unknown-host',
        'anything',
        $HOST_TOKENS
    )
);
