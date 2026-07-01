#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/testlib.php';
require __DIR__ . '/../../templates/oderland/lib/compatibility.php';

assertSame(true, telemetry_hash_equals('secret', 'secret'));
assertSame(false, telemetry_hash_equals('secret', 'wrong'));
assertSame(false, telemetry_hash_equals('secret', 'secret-extra'));

echo "compatibility tests passed\n";
