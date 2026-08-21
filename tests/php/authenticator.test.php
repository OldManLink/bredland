#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/authenticator.php';

$runner = new TestSuiteRunner('authenticator');

$runner->test('accepts matching token for known host', function () {
    $authenticator = new Authenticator(
        array(
            'bredland' => 'bredland.v1.test-token'
        )
    );

    assertTrue(
        $authenticator->authenticate(
            'bredland',
            'bredland.v1.test-token'
        )
    );
});

$runner->test('rejects incorrect token for known host', function () {
    $authenticator = new Authenticator(
        array(
            'bredland' => 'bredland.v1.test-token'
        )
    );

    assertFalse(
        $authenticator->authenticate(
            'bredland',
            'wrong-token'
        )
    );
});

$runner->test('rejects unknown host', function () {
    $authenticator = new Authenticator(
        array(
            'bredland' => 'bredland.v1.test-token'
        )
    );

    assertFalse(
        $authenticator->authenticate(
            'no-such-host',
            'bredland.v1.test-token'
        )
    );
});

$runner->test('authenticates multiple configured hosts', function () {
    $authenticator = new Authenticator(
        array(
            'mikrotik' => 'mikrotik.v1.test-token',
            'bredland' => 'bredland.v1.test-token'
        )
    );

    assertTrue(
        $authenticator->authenticate(
            'mikrotik',
            'mikrotik.v1.test-token'
        )
    );

    assertTrue(
        $authenticator->authenticate(
            'bredland',
            'bredland.v1.test-token'
        )
    );
});

$runner->finish();
