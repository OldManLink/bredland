#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot . '/notification-val.php';

$runner = new TestSuiteRunner('NotificationVal');

$runner->test('compiles plain notification', function () {
    $result = NotificationVal::compile(
        'Software update available',
        test_schema(),
        'Happy Path'
    );

    assert_compile_success($result);

    $notification = $result->value()->render(array());

    assertSame('Software update available', $notification->text());
    assertFalse($notification->has_resolution());
});

$runner->test('compiles notification with resolution', function () {
    $result = NotificationVal::compile(
        array(
            'RouterOS {{latest_version}} is available',
            'install-routeros-update'
        ),
        test_schema(),
        'Happy Path'
    );

    assert_compile_success($result);

    $notification = $result->value()->render(
        array('latest_version' => '7.23.2')
    );

    assertSame('RouterOS 7.23.2 is available', $notification->text());
    assertTrue($notification->has_resolution());
    assertSame('install-routeros-update', $notification->resolution());
});

$runner->test('rejects unknown field in notification text', function () {
    assert_compile_error(
        NotificationVal::compile(
            array(
                'RouterOS {{banana}} is available',
                'install-routeros-update'
            ),
            test_schema(),
            'notification'
        ),
        "notification[0][1]: 'banana' must exist in schema"
    );
});

$runner->test('rejects empty array', function () {
    assert_compile_error(
        NotificationVal::compile(
            array(),
            test_schema(),
            'notification'
        ),
        'notification: must be a string or a two-element array'
    );
});

$runner->test('rejects one-element array', function () {
    assert_compile_error(
        NotificationVal::compile(
            array('Software update available'),
            test_schema(),
            'notification'
        ),
        'notification: must be a string or a two-element array'
    );
});

$runner->test('rejects three-element array', function () {
    assert_compile_error(
        NotificationVal::compile(
            array(
                'Software update available',
                'install-routeros-update',
                'fubar'
            ),
            test_schema(),
            'notification'
        ),
        'notification: must be a string or a two-element array'
    );
});

$runner->test('rejects object', function () {
    assert_compile_error(
        NotificationVal::compile(
            array(
                'text' => 'Software update available',
                'resolution' => 'install-routeros-update'
            ),
            test_schema(),
            'notification'
        ),
        'notification: must be a non-empty string'
    );
});

$runner->test('rejects non-string resolution', function () {
    assert_compile_error(
        NotificationVal::compile(
            array(
                'Software update available',
                42
            ),
            test_schema(),
            'notification'
        ),
        'notification[1]: must be a non-empty string'
    );
});

$runner->test('rejects empty resolution', function () {
    assert_compile_error(
        NotificationVal::compile(
            array(
                'Software update available',
                ''
            ),
            test_schema(),
            'notification'
        ),
        'notification[1]: must be a non-empty string'
    );
});

$runner->test('rejects null', function () {
    assert_compile_error(
        NotificationVal::compile(
            null,
            test_schema(),
            'notification'
        ),
        'notification: must be a non-empty string'
    );
});

$runner->finish();