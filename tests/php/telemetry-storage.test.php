#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/telemetry-storage.php';

$runner = new TestSuiteRunner('telemetry-storage');

$runner->test('appends record to daily host file', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-storage-' . uniqid();

    $storage = new TelemetryStorage($dataDir);

    $storage->append(
        'bredland',
        '2026-08-19',
        array(
            'schema' => 1,
            'host' => 'bredland',
            'uptime' => 12345
        )
    );

    $file = $dataDir . '/bredland-2026-08-19.jsonl';

    assertTrue(is_dir($dataDir));
    assertTrue(file_exists($file));
    assertSame(
        "{\"schema\":1,\"host\":\"bredland\",\"uptime\":12345}\n",
        file_get_contents($file)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('appends without replacing existing records', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-storage-' . uniqid();

    $storage = new TelemetryStorage($dataDir);

    $storage->append(
        'bredland',
        '2026-08-19',
        array(
            'schema' => 1,
            'uptime' => 12345
        )
    );

    $storage->append(
        'bredland',
        '2026-08-19',
        array(
            'schema' => 1,
            'uptime' => 12346
        )
    );

    $file = $dataDir . '/bredland-2026-08-19.jsonl';

    assertSame(
        "{\"schema\":1,\"uptime\":12345}\n" .
        "{\"schema\":1,\"uptime\":12346}\n",
        file_get_contents($file)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('sanitizes host in daily filename', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-storage-' . uniqid();

    $storage = new TelemetryStorage($dataDir);

    $storage->append(
        'bred/land',
        '2026-08-19',
        array(
            'schema' => 1
        )
    );

    $file = $dataDir . '/bred_land-2026-08-19.jsonl';

    assertTrue(file_exists($file));

    unlink($file);
    rmdir($dataDir);
});

$runner->finish();
