#!/usr/bin/env php
<?php

declare(strict_types=1);

require getenv('TEST_CONFIG');
require __DIR__ . '/lib/testlib.php';
require __DIR__ . '/../../templates/noc/lib/telemetry.php';

$tmpdir = sys_get_temp_dir() . '/bredland-telemetry-test-' . getmypid();
mkdir($tmpdir);

$file = $tmpdir . '/bredland-2026-07-01.jsonl';

file_put_contents(
    $file,
    "{\"host\":\"bredland\",\"status\":\"online\",\"seq\":1}\n" .
    "{\"host\":\"bredland\",\"status\":\"online\",\"seq\":2}\n" .
    "\n"
);

assertSame(
    "{\"host\":\"bredland\",\"status\":\"online\",\"seq\":2}",
    latest_jsonl_line($file)
);

unlink($file);

$missing_file = $tmpdir . '/missing.jsonl';

assertSame(
    'unavailable',
    latest_jsonl_line($missing_file)
);

$empty_file = $tmpdir . '/empty.jsonl';
file_put_contents($empty_file, "");

assertSame(
    'empty',
    latest_jsonl_line($empty_file)
);
unlink($empty_file);

rmdir($tmpdir);
