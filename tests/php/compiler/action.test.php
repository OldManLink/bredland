#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/action.php';

$runner = new TestRunner('action');

$runner->test('compiles setHealth action', function () {
    $action = from_json(<<<'JSON'
    {
        "receiver": "client",
        "method": "setHealth",
        "argument": "critical"
    }
JSON
    );

    $result = Action::compile($action, test_schema(), 'Happy Path');
    assert_compile_success($result);
    $value = $result->value();

    assertSame('client', $value->receiver()->name());
    assertSame(Client::class, $value->receiver()->receiver_class());

    assertSame('setHealth', $value->method()->name());
    assertSame(HealthVal::class, $value->method()->argument_class());

    assertSame('critical', $value->argument()->value());
});


$runner->test('compiles addNotification action', function () {
    $action = from_json(<<<'JSON'
    {
        "receiver": "client",
        "method": "addNotification",
        "argument": "Disk space is low"
    }
JSON
    );

    $result = Action::compile($action, test_schema(), 'Happy Path');
    assert_compile_success($result);
    $value = $result->value();

    assertSame('client', $value->receiver()->name());
    assertSame(Client::class, $value->receiver()->receiver_class());

    assertSame('addNotification', $value->method()->name());
    assertSame(SlotVal::class, $value->method()->argument_class());
});


$runner->test('rejects unsupported receiver', function () {
    $action = from_json(<<<'JSON'
    {
        "receiver": "router",
        "method": "setHealth",
        "argument": "critical"
    }
JSON
    );
    assert_compile_error(Action::compile($action, test_schema(), 'action'), "action.receiver: unsupported receiver: router");
});


$runner->test('rejects unsupported method', function () {
    $action = from_json(<<<'JSON'
    {
        "receiver": "client",
        "method": "reboot",
        "argument": "now"
    }
JSON
    );
    assert_compile_error(Action::compile($action, test_schema(), 'action'), "action.method: unsupported method: reboot");
});


$runner->test('rejects invalid method argument', function () {
    $action = from_json(<<<'JSON'
    {
        "receiver": "client",
        "method": "setHealth",
        "argument": "hungover"
    }
JSON
    );
    assert_compile_error(Action::compile($action, test_schema(), 'action'), "action.argument: unsupported health value: hungover");
});

$runner->finish();