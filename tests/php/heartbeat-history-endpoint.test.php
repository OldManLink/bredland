#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/heartbeat-history-endpoint.php';
require_once $nocRoot . '/lib/heartbeat-history.php';

function heartbeat_history_endpoint($dataDir = null, $maxPageSize=288) {
    return new HeartbeatHistoryEndpoint(
        new Authenticator(
            array(
                'bredland' => 'bredland.v1.test-token'
            )
        ),
        new HeartbeatHistory($dataDir),
        $maxPageSize
    );
}

$runner = new TestSuiteRunner('heartbeat-history-endpoint');

$runner->test('rejects non-POST requests', function () {
    $endpoint = heartbeat_history_endpoint();

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
    $endpoint = heartbeat_history_endpoint();

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
    $endpoint = heartbeat_history_endpoint();

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
    $endpoint = heartbeat_history_endpoint();

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

$runner->test('rejects POST requests with invalid token', function () {
    $endpoint = heartbeat_history_endpoint();

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

$runner->test('returns latest heartbeat as JSON', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-endpoint-' . uniqid();

    mkdir($dataDir);
    $endpoint = heartbeat_history_endpoint($dataDir);

    $file = $dataDir . '/bredland-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n"
    );

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '1'
        )
    );

    assertSame(200, $response->status());
    assertSame(
        'application/json; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "{\"records\":[{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}],\"count\":1}\n",
        $response->body()
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('rejects POST requests with missing query', function () {
    $endpoint = heartbeat_history_endpoint();

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
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing query\n",
        $response->body()
    );
});

$runner->test('rejects non-integer latest', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => 'banana'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "invalid parameter: latest\n",
        $response->body()
    );
});

$runner->test('rejects zero latest', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '0'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "invalid parameter: latest\n",
        $response->body()
    );
});

$runner->test('rejects negative latest', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '-1'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "invalid parameter: latest\n",
        $response->body()
    );
});

$runner->test('rejects invalid starting_at timestamp', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '2',
            'starting_at' => 'not-a-timestamp'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "invalid parameter: starting_at\n",
        $response->body()
    );
});

$runner->test('rejects starting_at with range query', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'from' => '2026-09-19T12:00:00Z',
            'to' => '2026-09-19T12:10:00Z',
            'starting_at' => '2026-09-19T12:05:00Z'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "starting_at requires latest\n",
        $response->body()
    );
});

$runner->test('pages latest above server maximum', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-endpoint-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/bredland-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:10:00Z\",\"value\":3}\n"
    );

    $endpoint = heartbeat_history_endpoint($dataDir, 2);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '3'
        )
    );

    assertSame(200, $response->status());
    assertSame(
        'application/json; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        array(
            'records' => array(
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:05:00Z',
                    'value' => 2
                ),
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:10:00Z',
                    'value' => 3
                )
            ),
            'count' => 2,
            'next' => array(
                'starting_at' => '2026-09-19T12:00:00Z'
            )
        ),
        json_decode($response->body(), true)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('continues latest from starting_at timestamp', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-endpoint-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/bredland-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:10:00Z\",\"value\":3}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:15:00Z\",\"value\":4}\n"
    );

    $endpoint = heartbeat_history_endpoint($dataDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '2',
            'starting_at' => '2026-09-19T12:05:00Z'
        )
    );

    assertSame(200, $response->status());
    assertSame(
        array(
            'records' => array(
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:00:00Z',
                    'value' => 1
                ),
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:05:00Z',
                    'value' => 2
                )
            ),
            'count' => 2
        ),
        json_decode($response->body(), true)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('continues paged latest from starting_at timestamp', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-endpoint-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/bredland-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:10:00Z\",\"value\":3}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:15:00Z\",\"value\":4}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:20:00Z\",\"value\":5}\n"
    );

    $endpoint = heartbeat_history_endpoint($dataDir, 2);


    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '3',
            'starting_at' => '2026-09-19T12:15:00Z'
        )
    );

    assertSame(200, $response->status());
    assertSame(
        array(
            'records' => array(
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:10:00Z',
                    'value' => 3
                ),
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:15:00Z',
                    'value' => 4
                )
            ),
            'count' => 2,
            'next' => array(
                'starting_at' => '2026-09-19T12:05:00Z'
            )
        ),
        json_decode($response->body(), true)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('returns empty JSON array when history is empty', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-endpoint-' . uniqid();

    mkdir($dataDir);

    $endpoint = heartbeat_history_endpoint($dataDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '1'
        )
    );

    assertSame(200, $response->status());
    assertSame(
        'application/json; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "{\"records\":[],\"count\":0}\n",
        $response->body()
    );

    rmdir($dataDir);
});

$runner->test('returns heartbeats in inclusive time range as JSON', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-endpoint-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/bredland-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T11:55:00Z\",\"value\":1}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":2}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":3}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:10:00Z\",\"value\":4}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:15:00Z\",\"value\":5}\n"
    );

    $endpoint = heartbeat_history_endpoint($dataDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'from' => '2026-09-19T12:00:00Z',
            'to' => '2026-09-19T12:10:00Z'
        )
    );

    assertSame(200, $response->status());
    assertSame(
        'application/json; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        array(
            'records' => array(
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:00:00Z',
                    'value' => 2
                ),
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:05:00Z',
                    'value' => 3
                ),
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:10:00Z',
                    'value' => 4
                )
            ),
            'count' => 3
        ),
        json_decode($response->body(), true)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('rejects range query with missing to', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'from' => '2026-09-19T12:00:00Z'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing parameter: to\n",
        $response->body()
    );
});

$runner->test('rejects range query with missing from', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'to' => '2026-09-19T12:10:00Z'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing parameter: from\n",
        $response->body()
    );
});

$runner->test('rejects range query with invalid from timestamp', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'from' => 'not-a-timestamp',
            'to' => '2026-09-19T12:10:00Z'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "invalid parameter: from\n",
        $response->body()
    );
});

$runner->test('rejects range query with invalid to timestamp', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'from' => '2026-09-19T12:00:00Z',
            'to' => 'not-a-timestamp'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "invalid parameter: to\n",
        $response->body()
    );
});

$runner->test('rejects range with from after to', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'from' => '2026-09-19T12:10:00Z',
            'to' => '2026-09-19T12:00:00Z'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "invalid range\n",
        $response->body()
    );
});

$runner->test('pages range above server maximum', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-endpoint-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/bredland-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:00:00Z\",\"value\":1}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:05:00Z\",\"value\":2}\n" .
        "{\"host\":\"bredland\",\"ts\":\"2026-09-19T12:10:00Z\",\"value\":3}\n"
    );

    $endpoint = heartbeat_history_endpoint($dataDir, 2);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'from' => '2026-09-19T12:00:00Z',
            'to' => '2026-09-19T12:10:00Z'
        )
    );

    assertSame(200, $response->status());
    assertSame(
        'application/json; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        array(
            'records' => array(
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:05:00Z',
                    'value' => 2
                ),
                array(
                    'host' => 'bredland',
                    'ts' => '2026-09-19T12:10:00Z',
                    'value' => 3
                )
            ),
            'count' => 2,
            'next' => array(
                'to' => '2026-09-19T12:00:00Z'
            )
        ),
        json_decode($response->body(), true)
    );

    unlink($file);
    rmdir($dataDir);
});

$runner->test('rejects request with multiple query forms', function () {
    $endpoint = heartbeat_history_endpoint();

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '1',
            'from' => '2026-09-19T12:00:00Z',
            'to' => '2026-09-19T12:10:00Z'
        )
    );

    assertSame(400, $response->status());
    assertSame(
        "multiple queries\n",
        $response->body()
    );
});

$runner->test('preserves canonical heartbeat value types', function () {
    $dataDir = sys_get_temp_dir() .
        '/bredland-history-endpoint-' . uniqid();

    mkdir($dataDir);

    $file = $dataDir . '/bredland-2026-09-19.jsonl';

    file_put_contents(
        $file,
        "{\"host\":\"bredland\"," .
        "\"ts\":\"2026-09-19T12:00:00Z\"," .
        "\"integer_value\":42," .
        "\"float_value\":47.2," .
        "\"string_value\":\"hello\"," .
        "\"boolean_value\":true}\n"
    );

    $endpoint = heartbeat_history_endpoint($dataDir);

    $response = $endpoint->handle(
        array(
            'REQUEST_METHOD' => 'POST'
        ),
        array(
            'host' => 'bredland',
            'token' => 'bredland.v1.test-token',
            'latest' => '1'
        )
    );

    $records = json_decode($response->body(), true)["records"];

    assertSame(42, $records[0]['integer_value']);
    assertSame(47.2, $records[0]['float_value']);
    assertSame('hello', $records[0]['string_value']);
    assertSame(true, $records[0]['boolean_value']);

    unlink($file);
    rmdir($dataDir);
});

$runner->finish();