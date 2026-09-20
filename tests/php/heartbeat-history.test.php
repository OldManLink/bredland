#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/heartbeat-history.php';

$runner = new TestSuiteRunner('heartbeat-history');

$runner->test('returns latest record from daily history', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T12:05:00Z',
                'value' => 2
            )
        ),
        $history->latest('mikrotik', 1)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('returns latest records from daily history', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:10:00Z\",\"value\":3}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T12:05:00Z',
                'value' => 2
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T12:10:00Z',
                'value' => 3
            )
        ),
        $history->latest('mikrotik', 2)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('returns latest records across daily history files', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $olderFile = $dataDir . '/mikrotik-2026-09-18.jsonl';
    $newerFile = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $olderFile,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-18T23:55:00Z\",\"value\":1}\n"
    );

    file_put_contents(
        $newerFile,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T00:00:00Z\",\"value\":2}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T00:05:00Z\",\"value\":3}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-18T23:55:00Z',
                'value' => 1
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T00:00:00Z',
                'value' => 2
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T00:05:00Z',
                'value' => 3
            )
        ),
        $history->latest('mikrotik', 3)
    );

    unlink($olderFile);
    unlink($newerFile);
    rmdir($dataDir);
});

$runner->test('returns latest record from compressed daily history', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $plainFile = $dataDir . '/mikrotik-2026-09-18.jsonl';
    $file = $plainFile . '.gz';

    file_put_contents(
        $plainFile,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-18T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-18T12:05:00Z\",\"value\":2}\n"
    );

    exec('gzip ' . escapeshellarg($plainFile));

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-18T12:05:00Z',
                'value' => 2
            )
        ),
        $history->latest('mikrotik', 1)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('returns latest record from compressed monthly history', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $plainFile = $dataDir . '/mikrotik-2026-08.jsonl';
    $file = $plainFile . '.gz';

    file_put_contents(
        $plainFile,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-08-31T23:50:00Z\",\"value\":1}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-08-31T23:55:00Z\",\"value\":2}\n"
    );

    exec('gzip ' . escapeshellarg($plainFile));

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-08-31T23:55:00Z',
                'value' => 2
            )
        ),
        $history->latest('mikrotik', 1)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('orders monthly and daily history chronologically', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $monthlyPlain = $dataDir . '/mikrotik-2026-08.jsonl';
    $monthlyFile = $monthlyPlain . '.gz';

    file_put_contents(
        $monthlyPlain,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-08-31T23:55:00Z\",\"value\":1}\n"
    );

    exec('gzip ' . escapeshellarg($monthlyPlain));

    $dailyPlain = $dataDir . '/mikrotik-2026-09-18.jsonl';
    $dailyFile = $dailyPlain . '.gz';

    file_put_contents(
        $dailyPlain,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-18T23:55:00Z\",\"value\":2}\n"
    );

    exec('gzip ' . escapeshellarg($dailyPlain));

    $currentFile = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $currentFile,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T00:05:00Z\",\"value\":3}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-08-31T23:55:00Z',
                'value' => 1
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-18T23:55:00Z',
                'value' => 2
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T00:05:00Z',
                'value' => 3
            )
        ),
        $history->latest('mikrotik', 3)
    );

    unlink($monthlyFile);
    unlink($dailyFile);
    unlink($currentFile);
    rmdir($dataDir);
});

$runner->test('returns latest records across monthly daily and current history', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $monthlyPlain = $dataDir . '/mikrotik-2026-08.jsonl';
    $monthlyFile = $monthlyPlain . '.gz';

    file_put_contents(
        $monthlyPlain,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-08-31T23:55:00Z\",\"value\":1}\n"
    );

    exec('gzip ' . escapeshellarg($monthlyPlain));

    $dailyPlain = $dataDir . '/mikrotik-2026-09-18.jsonl';
    $dailyFile = $dailyPlain . '.gz';

    file_put_contents(
        $dailyPlain,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-18T23:55:00Z\",\"value\":2}\n"
    );

    exec('gzip ' . escapeshellarg($dailyPlain));

    $currentFile = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $currentFile,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T00:05:00Z\",\"value\":3}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-08-31T23:55:00Z',
                'value' => 1
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-18T23:55:00Z',
                'value' => 2
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T00:05:00Z',
                'value' => 3
            )
        ),
        $history->latest('mikrotik', 3)
    );

    unlink($monthlyFile);
    unlink($dailyFile);
    unlink($currentFile);
    rmdir($dataDir);
});

$runner->test('returns history only for requested host', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $mikrotikFile = $dataDir . '/mikrotik-2026-09-19.jsonl';
    $bredlandFile = $dataDir . '/bredland-2026-09-19.jsonl';

    file_put_contents(
        $mikrotikFile,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n"
    );

    file_put_contents(
        $bredlandFile,
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":2}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T12:00:00Z',
                'value' => 1
            )
        ),
        $history->latest('mikrotik', 1)
    );

    unlink($mikrotikFile);
    unlink($bredlandFile);
    rmdir($dataDir);
});

$runner->test('returns records in inclusive time range', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T11:55:00Z\",\"value\":1}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":2}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":3}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:10:00Z\",\"value\":4}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:15:00Z\",\"value\":5}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T12:00:00Z',
                'value' => 2
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T12:05:00Z',
                'value' => 3
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T12:10:00Z',
                'value' => 4
            )
        ),
        $history->range(
            'mikrotik',
            '2026-09-19T12:00:00Z',
            '2026-09-19T12:10:00Z',
            100
        )
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('rejects range exceeding maximum', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:10:00Z\",\"value\":3}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertThrows(
        'InvalidArgumentException',
        'range exceeds maximum: 2',
        function () use ($history) {
            $history->range(
                'mikrotik',
                '2026-09-19T12:00:00Z',
                '2026-09-19T12:10:00Z',
                2
            );
        }
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('returns range across monthly daily and current history', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $monthlyPlain = $dataDir . '/mikrotik-2026-08.jsonl';
    $monthlyFile = $monthlyPlain . '.gz';

    file_put_contents(
        $monthlyPlain,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-08-31T23:55:00Z\",\"value\":1}\n"
    );

    exec('gzip ' . escapeshellarg($monthlyPlain));

    $dailyPlain = $dataDir . '/mikrotik-2026-09-18.jsonl';
    $dailyFile = $dailyPlain . '.gz';

    file_put_contents(
        $dailyPlain,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-18T23:55:00Z\",\"value\":2}\n"
    );

    exec('gzip ' . escapeshellarg($dailyPlain));

    $currentFile = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $currentFile,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T00:05:00Z\",\"value\":3}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-18T23:55:00Z',
                'value' => 2
            ),
            array(
                'host' => 'mikrotik',
                'ts' => '2026-09-19T00:05:00Z',
                'value' => 3
            )
        ),
        $history->range(
            'mikrotik',
            '2026-09-18T23:55:00Z',
            '2026-09-19T00:05:00Z',
            100
        )
    );

    unlink($monthlyFile);
    unlink($dailyFile);
    unlink($currentFile);
    rmdir($dataDir);
});

$runner->test('returns empty range when no records match', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"mikrotik\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n"
    );

    $history = new HeartbeatHistory($dataDir);

    assertSame(
        array(),
        $history->range(
            'mikrotik',
            '2026-09-19T13:00:00Z',
            '2026-09-19T14:00:00Z',
            100
        )
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->finish();
