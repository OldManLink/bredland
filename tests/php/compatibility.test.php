#!/usr/bin/env php
<?php
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/compatibility.php';

assertTrue(telemetry_hash_equals('secret', 'secret'));
assertFalse(telemetry_hash_equals('secret', 'wrong'));
assertFalse(telemetry_hash_equals('secret', 'secret-extra'));
