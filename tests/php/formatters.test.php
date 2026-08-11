#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/formatters.php';

assertSame('1.0 KiB', display_memory(1024));
assertSame('1.0 MiB', display_memory(1048576));
assertSame('1.0 GiB', display_memory(1073741824));

assertSame('0s', display_uptime(0));
assertSame('59s', display_uptime(59));
assertSame('01:00', display_uptime(60));
assertSame('1d00:00:00', display_uptime(86400));
assertSame('1w6d11:48:47', display_uptime(1165727));
