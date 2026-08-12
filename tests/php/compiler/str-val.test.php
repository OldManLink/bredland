#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/str-val.php';

$runner = new TestRunner('StrVal');

$runner->test('instance creation', function () {
    $fortyTwo = new StrVal('42');

    assertSame('42', $fortyTwo->value());
});

$runner->test('renders its value unchanged', function () {
    $value = new StrVal('RouterOS is available');
    assertSame('RouterOS is available', $value->render(array('latest_version' => '7.23.2')));
});

$runner->test('compiler tests: StrVal', function () {
    $result = StrVal::compile('42', test_schema(), 'Happy Path');
    assert_compile_success($result);

    assertSame('42', $result->value()->value());
    assertSame('string', $result->value()->value_type());
});

$runner->test('rejects null', function () {
    assert_compile_error(StrVal::compile(null, test_schema(), 'null'), 'null: must be a non-empty string');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(StrVal::compile('', test_schema(), '""'), '"": must be a non-empty string');
});

$runner->test('rejects boolean value type', function () {
    assert_compile_error(StrVal::compile(true, test_schema(), 'true'), 'true: must be a non-empty string');
});

$runner->test('rejects integer value type', function () {
    assert_compile_error(StrVal::compile(42, test_schema(), '42'), '42: must be a non-empty string');
});

$runner->test('rejects float value type', function () {
    assert_compile_error(StrVal::compile(42.0, test_schema(), '42.0'), '42.0: must be a non-empty string');
});

$runner->test('rejects array value type', function () {
    assert_compile_error(StrVal::compile(array(), test_schema(), 'array()'), 'array(): must be a non-empty string');
});

$runner->finish();