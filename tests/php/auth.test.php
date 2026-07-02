#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/testlib.php';
require __DIR__ . '/../../templates/oderland/telemetry.config.template.php';
require __DIR__ . '/../../templates/oderland/lib/compatibility.php';
require __DIR__ . '/../../templates/oderland/lib/auth.php';

assertSame(
    true,
    authenticate(
        '__MIKROTIK_NOC_HOST__',
        '__MIKROTIK_NOC_TOKEN__',
        $HOST_TOKENS
    )
);

assertSame(
    true,
    authenticate(
        '__BREDLAND_NOC_HOST__',
        '__BREDLAND_NOC_TOKEN__',
        $HOST_TOKENS
    )
);

assertSame(
    false,
    authenticate(
        '__MIKROTIK_NOC_HOST__',
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
