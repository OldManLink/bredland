#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/notification.php';

$runner = new TestSuiteRunner('notification');

$runner->test('returns constructor text', function () {
    $notification = new Notification('This is a test!');

    assertSame('This is a test!', $notification->text());
});

$runner->test('multiple notifications are independent', function () {
    $first = new Notification('One');
    $second = new Notification('Two');

    assertSame('One', $first->text());
    assertSame('Two', $second->text());
});

$runner->finish();
