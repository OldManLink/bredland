#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/timestamp.php';

$runner = new TestSuiteRunner('timestamp');

$runner->test('accepts canonical NOC timestamp', function () {
    assertTrue(
        Timestamp::is_valid('2026-09-19T12:34:56Z'),
        'expected valid timestamp to be accepted'
    );
});

$runner->test('rejects malformed timestamp', function () {
    assertTrue(
        !Timestamp::is_valid('not-a-timestamp'),
        'expected invalid timestamp to be rejected'

    );
});

$runner->test('rejects impossible timestamp', function () {
    assertTrue(
        !Timestamp::is_valid('2026-99-99T77:88:99Z'),
        'expected impossible timestamp to be rejected'
    );
});

$runner->finish();
