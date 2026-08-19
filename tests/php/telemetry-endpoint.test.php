#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) .
    '/templates/noc/lib/telemetry-response.php';
require_once dirname(dirname(__DIR__)) .
    '/templates/noc/lib/telemetry-endpoint.php';

function telemetry_endpoint() {
    return new TelemetryEndpoint(
        new Authenticator(
            array(
                'bredland' => 'bredland.v1.test-token'
            )
        )
    );
}

$runner = new TestSuiteRunner('telemetry-endpoint');

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
    $endpoint = new TelemetryEndpoint(
        new Authenticator(
            array(
                'bredland' => 'bredland.v1.test-token'
            )
        )
    );

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

$runner->finish();
