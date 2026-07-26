#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/int-val.php';

$runner = new TestRunner('IntVal');

$runner->test('instance creation', function () {
    $fortyTwo = new IntVal(42);

    assertSame(42, $fortyTwo->value());
});

$runner->test('compiler tests: IntVal', function () {
    $result = IntVal::compile(42, test_schema(), 'Happy Path');
    assert_compile_success($result);

    assertSame(42, $result->value()->value());
    assertSame('integer', $result->value()->value_type());
});

$runner->test('rejects null', function () {
    assert_compile_error(IntVal::compile(null, test_schema(), 'null'), 'null: must be an integer');
});

$runner->test('rejects boolean value type', function () {
    assert_compile_error(IntVal::compile(true, test_schema(), 'true'), 'true: must be an integer');
});

$runner->test('rejects float value type', function () {
    assert_compile_error(IntVal::compile(42.0, test_schema(), '42.0'), '42.0: must be an integer');
});

$runner->test('rejects string value type', function () {
    assert_compile_error(IntVal::compile('42', test_schema(), '"42"'), '"42": must be an integer');
});

$runner->test('rejects array value type', function () {
    assert_compile_error(IntVal::compile(array(), test_schema(), 'array()'), 'array(): must be an integer');
});

$runner->finish();