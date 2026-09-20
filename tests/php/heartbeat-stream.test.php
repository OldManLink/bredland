#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/heartbeat-stream.php';

$runner = new TestSuiteRunner('heartbeat-stream');

$runner->test('streams records newest first across files', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-heartbeat-stream-' . uniqid();

    mkdir($dataDir);

    $olderFile = $dataDir . '/mikrotik-2026-09-18.jsonl';
    $newerFile = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $olderFile,
        "{\"ts\":\"2026-09-18T23:50:00Z\",\"value\":1}\n" .
        "{\"ts\":\"2026-09-18T23:55:00Z\",\"value\":2}\n"
    );

    file_put_contents(
        $newerFile,
        "{\"ts\":\"2026-09-19T00:00:00Z\",\"value\":3}\n" .
        "{\"ts\":\"2026-09-19T00:05:00Z\",\"value\":4}\n"
    );

    try {
        $stream = new HeartbeatStream(
            array(
                $olderFile,
                $newerFile
            )
        );

        $records = array();

        foreach ($stream as $record) {
            $records[] = $record;
        }

        assertSame(
            array(
                array(
                    'ts' => '2026-09-19T00:05:00Z',
                    'value' => 4
                ),
                array(
                    'ts' => '2026-09-19T00:00:00Z',
                    'value' => 3
                ),
                array(
                    'ts' => '2026-09-18T23:55:00Z',
                    'value' => 2
                ),
                array(
                    'ts' => '2026-09-18T23:50:00Z',
                    'value' => 1
                )
            ),
            $records
        );
    } finally {
        unlink($olderFile);
        unlink($newerFile);
        rmdir($dataDir);
    }
});

$runner->test('streams records newest first from compressed file', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-heartbeat-stream-' . uniqid();

    mkdir($dataDir);

    $plainFile = $dataDir . '/mikrotik-2026-09-18.jsonl';
    $file = $plainFile . '.gz';

    file_put_contents(
        $plainFile,
        "{\"ts\":\"2026-09-18T23:50:00Z\",\"value\":1}\n" .
        "{\"ts\":\"2026-09-18T23:55:00Z\",\"value\":2}\n"
    );

    exec('gzip ' . escapeshellarg($plainFile));

    try {
        $stream = new HeartbeatStream(
            array($file)
        );

        $records = array();

        foreach ($stream as $record) {
            $records[] = $record;
        }

        assertSame(
            array(
                array(
                    'ts' => '2026-09-18T23:55:00Z',
                    'value' => 2
                ),
                array(
                    'ts' => '2026-09-18T23:50:00Z',
                    'value' => 1
                )
            ),
            $records
        );
    } finally {
        unlink($file);
        rmdir($dataDir);
    }
});

$runner->test('does not open older file until iteration reaches it', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-heartbeat-stream-' . uniqid();

    mkdir($dataDir);

    $olderFile = $dataDir . '/mikrotik-2026-09-18.jsonl';
    $newerFile = $dataDir . '/mikrotik-2026-09-19.jsonl';

    file_put_contents(
        $newerFile,
        "{\"ts\":\"2026-09-19T00:00:00Z\",\"value\":1}\n" .
        "{\"ts\":\"2026-09-19T00:05:00Z\",\"value\":2}\n"
    );

    $previousErrorReporting = error_reporting(E_ALL);

    set_error_handler(function ($severity, $message) {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        $stream = new HeartbeatStream(
            array(
                $olderFile,
                $newerFile
            )
        );

        $records = array();

        foreach ($stream as $record) {
            $records[] = $record;
            break;
        }

        assertSame(
            array(
                array(
                    'ts' => '2026-09-19T00:05:00Z',
                    'value' => 2
                )
            ),
            $records
        );
    } finally {
        restore_error_handler();
        error_reporting($previousErrorReporting);

        unlink($newerFile);
        rmdir($dataDir);
    }
});

$runner->finish();
