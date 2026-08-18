#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/float-val.php';

$runner = new TestSuiteRunner('FloatVal');

$runner->test('instance creation', function () {
    $forty_two = new FloatVal(42.0);

    assertSame(42.0, $forty_two->value());
});

$runner->test('renders its value unchanged', function () {
    $fortyTwo = new FloatVal(42.0);
    assertSame(42.0, $fortyTwo->render(array()));
});

$runner->test('compiler tests: FloatVal', function () {
    $result = FloatVal::compile(42.0, test_schema(), 'Happy Path');
    assert_compile_success($result);

    assertSame(42.0, $result->value()->value());
    assertSame('float', $result->value()->value_type());
});

$runner->test('rejects null', function () {
    assert_compile_error(FloatVal::compile(null, test_schema(), 'null'), 'null: must be a float');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(FloatVal::compile('', test_schema(), '""'), '"": must be a float');
});

$runner->test('rejects boolean value type', function () {
    assert_compile_error(FloatVal::compile(true, test_schema(), 'true'), 'true: must be a float');
});

$runner->test('rejects integer value type', function () {
    assert_compile_error(FloatVal::compile(42, test_schema(), '42'), '42: must be a float');
});

$runner->test('rejects string value type', function () {
    assert_compile_error(FloatVal::compile('42', test_schema(), '"42"'), '"42": must be a float');
});

$runner->test('rejects array value type', function () {
    assert_compile_error(FloatVal::compile(array(), test_schema(), 'array()'), 'array(): must be a float');
});

$runner->finish();