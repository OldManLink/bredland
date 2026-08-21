#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) .
    '/templates/noc/lib/telemetry-response.php';

$runner = new TestSuiteRunner('telemetry-response');

$runner->test('holds telemetry response values', function () {
    $response = new TelemetryResponse(
        400,
        'missing parameter: uptime'
    );

    assertSame(400, $response->status());
    assertSame(
        'text/plain; charset=utf-8',
        $response->content_type()
    );
    assertSame(
        "missing parameter: uptime\n",
        $response->body()
    );
});

$runner->finish();