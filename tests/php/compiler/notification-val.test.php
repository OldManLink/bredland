#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot . '/notification-val.php';

$runner = new TestSuiteRunner('NotificationVal');

$plainNotificationJson = from_json(<<<'JSON'
{
    "text": [
        "Software update available"
    ]
}
JSON
);

$notificationJson = from_json(<<<'JSON'
{
    "text": [
        "RouterOS ",
        {
            "field": "latest_version"
        },
        " is available"
    ],
    "resolution": "install-routeros-update"
}
JSON
);

$badNotificationJson = from_json(<<<'JSON'
{
    "text": [
        "RouterOS ",
        {
            "field": "banana"
        },
        " is available"
    ]
}
JSON
);

$badNotificationJson2 = from_json(<<<'JSON'
{
    "text": [
        "Software update available"
    ],
    "resolution": 42
}
JSON
);

$badNotificationJson3 = from_json(<<<'JSON'
{
    "text": [
        "Software update available"
    ],
    "resolution": ""
}
JSON
);

$runner->test('compiles plain notification', function () use ($plainNotificationJson) {
    $result = NotificationVal::compile(
        $plainNotificationJson,
        test_schema(),
        'Happy Path'
    );

    assert_compile_success($result);

    $notification = $result->value()->render(array());

    assertSame('Software update available', $notification->text());
    assertFalse($notification->has_resolution());
});

$runner->test('compiles notification with resolution', function () use ($notificationJson) {
    $result = NotificationVal::compile(
        $notificationJson,
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

$runner->test('rejects unknown field in notification text', function () use ($badNotificationJson) {
    assert_compile_error(
        NotificationVal::compile(
            $badNotificationJson,
            test_schema(),
            'notification'
        ),
        "notification.text[1].FieldVal: 'banana' must exist in schema"
    );
});

$runner->test('rejects array', function () {
    assert_compile_error(
        NotificationVal::compile(
            array('Software update available'),
            test_schema(),
            'notification'
        ),
        'notification: must be an object'
    );
});

$runner->test('rejects non-string resolution', function () use ($badNotificationJson2) {
    assert_compile_error(
        NotificationVal::compile(
            $badNotificationJson2,
            test_schema(),
            'notification'
        ),
        'notification.resolution: must be a non-empty string'
    );
});

$runner->test('rejects empty resolution', function () use ($badNotificationJson3) {
    assert_compile_error(
        NotificationVal::compile(
            $badNotificationJson3,
            test_schema(),
            'notification'
        ),
        'notification.resolution: must be a non-empty string'
    );
});

$runner->test('rejects null', function () {
    assert_compile_error(
        NotificationVal::compile(
            null,
            test_schema(),
            'notification'
        ),
        'notification: must be an object'
    );
});

$runner->finish();