#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/client.php';
require_once $compilerRoot .'/noc.php';
require_once $compilerRoot .'/receiver-val.php';

$runner = new TestRunner('ReceiverVal');

$runner->test('instance creation', function () {
    $client = new ReceiverVal('client', Client::class);
    $noc = new ReceiverVal('noc', Noc::class);

    assertSame('client', $client->name());
    assertSame(Client::class, $client->receiver_class());
    assertSame('noc', $noc->name());
    assertSame(Noc::class, $noc->receiver_class());
});

$runner->test('compiles client', function () {
    $result = ReceiverVal::compile('client', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('client', $result->value()->name());
    assertSame(Client::class, $result->value()->receiver_class());
    $methods = $result->value()->compilable_methods();
    assertSame(HealthVal::class, $methods['setHealth']);
    assertSame(SlotVal::class, $methods['addNotification']);
});

$runner->test('compiles noc', function () {
    $result = ReceiverVal::compile('noc', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('noc', $result->value()->name());
    assertSame(Noc::class, $result->value()->receiver_class());
    $methods = $result->value()->compilable_methods();
    assertSame(StrVal::class, $methods['setValue']);
});

$runner->test('rejects unsupported receiver', function () {
    assert_compile_error(ReceiverVal::compile('hacker', test_schema(), 'hacker'), 'hacker: unsupported receiver: hacker');
});

$runner->test('rejects null', function () {
    assert_compile_error(ReceiverVal::compile(null, test_schema(), 'null'), 'null.receiver: must be a non-empty string');
});

$runner->test('rejects boolean', function () {
    assert_compile_error(ReceiverVal::compile(true, test_schema(), 'true'), 'true.receiver: must be a non-empty string');
});

$runner->test('rejects integer', function () {
    assert_compile_error(ReceiverVal::compile(42, test_schema(), '42'), '42.receiver: must be a non-empty string');
});

$runner->test('rejects float', function () {
    assert_compile_error(ReceiverVal::compile(42.0, test_schema(), '42.0'), '42.0.receiver: must be a non-empty string');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(ReceiverVal::compile('', test_schema(), '""'), '"".receiver: must be a non-empty string');
});

$runner->test('rejects array', function () {
    assert_compile_error(ReceiverVal::compile(array(), test_schema(), 'array()'), 'array().receiver: must be a non-empty string');
});

$runner->finish();