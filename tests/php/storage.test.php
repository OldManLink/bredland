#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/testlib.php';
require __DIR__ . '/../../templates/oderland/telemetry.config.template.php';
require __DIR__ . '/../../templates/oderland/lib/storage.php';

assertSame(
        '/tmp/noc-data/bredland-2026-07-01.jsonl',
        daily_jsonl_filename('/tmp/noc-data', 'bredland', '2026-07-01')
);

assertSame(
        '/tmp/noc-data/mikrotik_test-2026-07-01.jsonl',
        daily_jsonl_filename('/tmp/noc-data/', 'mikrotik.test', '2026-07-01')
);

$tmpdir = sys_get_temp_dir() . '/bredland-storage-test-' . getmypid();
mkdir($tmpdir);

$file = $tmpdir . '/bredland-2026-07-01.jsonl';

$record = array(
    'schema' => TELEMETRY_SCHEMA_VERSION,
    'ts' => '2026-07-01T15:00:00Z',
    'host' => 'bredland',
);

append_record($file, $record);

assertSame(
    json_encode($record) . "\n",
    file_get_contents($file)
);

unlink($file);

$subdir = $tmpdir . '/data';

ensure_data_dir($subdir);

assertSame(true, is_dir($subdir));

ensure_data_dir($subdir);

assertSame(true, is_dir($subdir));

rmdir($subdir);
rmdir($tmpdir);

echo "storage tests passed\n";