#!/usr/bin/env php
<?php
require __DIR__ . '/lib/testlib.php';
$repoRoot = dirname(dirname(__DIR__));
require $repoRoot . '/templates/noc/lib/compatibility.php';

assertTrue(telemetry_hash_equals('secret', 'secret'));
assertFalse(telemetry_hash_equals('secret', 'wrong'));
assertFalse(telemetry_hash_equals('secret', 'secret-extra'));
