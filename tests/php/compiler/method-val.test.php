#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/health-val.php';
require_once $compilerRoot .'/slot-val.php';
require_once $compilerRoot .'/method-val.php';

$runner = new TestRunner('MethodVal');

$runner->test('instance creation', function () {
    $set_health = new MethodVal('setHealth', HealthVal::class);
    $add_notification = new MethodVal('addNotification', SlotVal::class);

    assertSame('setHealth', $set_health->name());
    assertSame(HealthVal::class, $set_health->argument_class());
    assertSame('addNotification', $add_notification->name());
    assertSame(SlotVal::class, $add_notification->argument_class());
});

$runner->test('renders method name', function () {
    $method = new MethodVal('addNotification', SlotVal::class);

    assertSame('addNotification', $method->render());
});

$runner->test('compiles setHealth', function () {
    $result = MethodVal::compile('setHealth', test_methods(), test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('setHealth', $result->value()->name());
    assertSame(HealthVal::class, $result->value()->argument_class());
});

$runner->test('compiles addNotification', function () {
    $result = MethodVal::compile('addNotification', test_methods(), test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('addNotification', $result->value()->name());
    assertSame(SlotVal::class, $result->value()->argument_class());
});

$runner->test('rejects invalid method definition', function () {
    $result = MethodVal::compile('broken', array('broken' => null), test_schema(), 'Happy Path');
    assert_compile_error($result, "Happy Path: invalid method definition: broken");
});

$runner->test('rejects unsupported method', function () {
    assert_compile_error(MethodVal::compile('formatRoot', test_methods(), test_schema(), 'formatRoot'), 'formatRoot: unsupported method: formatRoot');
});

$runner->test('rejects null', function () {
    assert_compile_error(MethodVal::compile(null, test_methods(), test_schema(), 'null'), 'null.method: must be a non-empty string');
});

$runner->test('rejects boolean', function () {
    assert_compile_error(MethodVal::compile(true, test_methods(), test_schema(), 'true'), 'true.method: must be a non-empty string');
});

$runner->test('rejects integer', function () {
    assert_compile_error(MethodVal::compile(42, test_methods(), test_schema(), '42'), '42.method: must be a non-empty string');
});

$runner->test('rejects float', function () {
    assert_compile_error(MethodVal::compile(42.0, test_methods(), test_schema(), '42.0'), '42.0.method: must be a non-empty string');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(MethodVal::compile('', test_methods(), test_schema(), '""'), '"".method: must be a non-empty string');
});

$runner->test('rejects array', function () {
    assert_compile_error(MethodVal::compile(array(), test_methods(), test_schema(), 'array()'), 'array().method: must be a non-empty string');
});

$runner->finish();