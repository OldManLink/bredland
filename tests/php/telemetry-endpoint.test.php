#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/telemetry-endpoint.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/record-builder.php';

$temporarySchemaDirs = array();
$temporaryDataDirs = array();

function telemetry_endpoint($schemasDir = null, $dataDir = null) {
    global $temporarySchemaDirs;
    global $temporaryDataDirs;

    if ($schemasDir === null) {
        $schemasDir = sys_get_temp_dir() .
            '/bredland-endpoint-schemas-' . uniqid();

        mkdir($schemasDir);
        $temporarySchemaDirs[] = $schemasDir;
    }

    if ($dataDir === null) {
        $dataDir = sys_get_temp_dir() .
            '/bredland-endpoint-data-' . uniqid();

        mkdir($dataDir);
        $temporaryDataDirs[] = $dataDir;
    }

    return new TelemetryEndpoint(
        new Authenticator(
            array(
                'bredland' => 'bredland.v1.test-token'
            )
        ),
        new SchemaLoader($schemasDir),
        new TelemetryStorage($dataDir)
    );
}

function cleanup_temporary_dirs() {
    global $temporarySchemaDirs;
    global $temporaryDataDirs;

    foreach ($temporarySchemaDirs as $dir) {
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    foreach ($temporaryDataDirs as $dir) {
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
}

register_shutdown_function('cleanup_temporary_dirs');

$runner = new TestSuiteRunner('telemetry-endpoint');

$runner->test('stores valid heartbeat', function () {
    $schemasDir = sys_get_temp_dir() .
        '/bredland-endpoint-schemas-' . uniqid();

    $dataDir = sys_get_temp_dir() .
        '/bredland-endpoint-data-' . uniqid();

    mkdir($schemasDir);
    mkdir($dataDir);

    file_put_contents(
        $schemasDir . '/bredland.json',
        json_encode(
            array(
                'schema' => array('const' => 1),
                'host' => array('const' => 'bredland'),
                'ts' => array('value_type' => 'string'),
                'uptime' => array('value_type' => 'integer'),
                'ttl' => array('value_type' => 'integer'),
                'temperature' => array('value_type' => 'float'),
                'remote_addr' => array('value_type' => 'string')
            )
        )
    );

    $endpoint = telemetry_endpoint($schemasDir, $dataDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '127.0.0.1'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => 'temperature',
            'temperature' => '47.2',
            'uptime' => '12345',
            'ttl' => '300'
        )
    );

    assertSame("ok\n", $response->body());
    assertSame(200, $response->status());
    assertSame("ok\n", $response->body());

    $file = $dataDir .
        '/bredland-' . gmdate('Y-m-d') . '.jsonl';

    assertTrue(file_exists($file));

    $record = json_decode(
        trim(file_get_contents($file)),
        true
    );

    assertSame(1, $record['schema']);
    assertSame('bredland', $record['host']);
    assertSame(12345, $record['uptime']);
    assertSame(300, $record['ttl']);
    assertSame(47.2, $record['temperature']);
    assertSame('127.0.0.1', $record['remote_addr']);

    unlink($file);
    unlink($schemasDir . '/bredland.json');
    rmdir($schemasDir);
    rmdir($dataDir);
});

$runner->test('rejects non-POST requests', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'GET'
        ),
        array()
    );

    assertSame(405, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "method not allowed\n",
        $response->body()
    );
});

$runner->test('rejects POST requests with missing host', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array()
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing parameter: host\n",
        $response->body()
    );
});

$runner->test('rejects POST requests with empty host', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => ''
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing parameter: host\n",
        $response->body()
    );
});

$runner->test('rejects POST requests with missing token', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing parameter: token\n",
        $response->body()
    );
});

$runner->test('rejects POST requests with empty token', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => ''
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing parameter: token\n",
        $response->body()
    );
});

$runner->test('rejects POST requests with invalid token', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'wrong-token'
        )
    );

    assertSame(403, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "forbidden\n",
        $response->body()
    );
});

$runner->test('accepts authentication before validating fields', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "missing parameter: fields\n",
        $response->body()
    );
});

$runner->test('rejects authenticated POST requests with empty fields', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => ''
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing parameter: fields\n",
        $response->body()
    );
});

$runner->test('rejects missing selected field', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => 'temperature'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "missing field: temperature\n",
        $response->body()
    );
});

$runner->test('rejects missing record schema', function () {
    $schemasDir = sys_get_temp_dir() .
        '/bredland-endpoint-schemas-' . uniqid();

    mkdir($schemasDir);

    $endpoint = telemetry_endpoint($schemasDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => 'temperature',
            'temperature' => '47.2'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "missing record schema: bredland\n",
        $response->body()
    );

    rmdir($schemasDir);
});

$runner->test('rejects invalid record schema', function () {
    $schemasDir = sys_get_temp_dir() .
        '/bredland-endpoint-schemas-' . uniqid();

    mkdir($schemasDir);

    file_put_contents(
        $schemasDir . '/bredland.json',
        '{not valid json'
    );

    $endpoint = telemetry_endpoint($schemasDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => 'temperature',
            'temperature' => '47.2'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "invalid record schema: bredland\n",
        $response->body()
    );

    unlink($schemasDir . '/bredland.json');
    rmdir($schemasDir);
});

$runner->test('rejects reserved selected field', function () {
    $endpoint = telemetry_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => 'ts',
            'ts' => 'fake'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "reserved field: ts\n",
        $response->body()
    );
});

$runner->test('rejects missing uptime', function () {
    $schemasDir = sys_get_temp_dir() .
        '/bredland-endpoint-schemas-' . uniqid();

    mkdir($schemasDir);

    file_put_contents(
        $schemasDir . '/bredland.json',
        json_encode(
            array(
                'schema' => array('const' => 1),
                'host' => array('const' => 'bredland'),
                'temperature' => array(
                    'value_type' => 'float'
                )
            )
        )
    );

    $endpoint = telemetry_endpoint($schemasDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => 'temperature',
            'temperature' => '47.2'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "missing parameter: uptime\n",
        $response->body()
    );

    unlink($schemasDir . '/bredland.json');
    rmdir($schemasDir);
});

$runner->test('rejects missing ttl', function () {
    $schemasDir = sys_get_temp_dir() .
        '/bredland-endpoint-schemas-' . uniqid();

    mkdir($schemasDir);

    file_put_contents(
        $schemasDir . '/bredland.json',
        json_encode(
            array(
                'schema' => array('const' => 1),
                'host' => array('const' => 'bredland'),
                'temperature' => array(
                    'value_type' => 'float'
                )
            )
        )
    );

    $endpoint = telemetry_endpoint($schemasDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => 'temperature',
            'temperature' => '47.2',
            'uptime' => '12345'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "missing parameter: ttl\n",
        $response->body()
    );

    unlink($schemasDir . '/bredland.json');
    rmdir($schemasDir);
});

$runner->test('rejects invalid field value', function () {
    $schemasDir = sys_get_temp_dir() .
        '/bredland-endpoint-schemas-' . uniqid();

    mkdir($schemasDir);

    file_put_contents(
        $schemasDir . '/bredland.json',
        json_encode(
            array(
                'schema' => array('const' => 1),
                'host' => array('const' => 'bredland'),
                'temperature' => array(
                    'value_type' => 'float'
                )
            )
        )
    );

    $endpoint = telemetry_endpoint($schemasDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'fields' => 'temperature',
            'temperature' => 'not-a-number',
            'uptime' => '12345',
            'ttl' => '300'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "invalid value for field temperature: expected float\n",
        $response->body()
    );

    unlink($schemasDir . '/bredland.json');
    rmdir($schemasDir);
});

$runner->finish();
