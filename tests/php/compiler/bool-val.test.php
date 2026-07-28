#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/bool-val.php';

$runner = new TestRunner('BoolVal');

$runner->test('instance creation', function () {
    $bool = new BoolVal(true);
    assertSame(true, $bool->value());
    $bool = new BoolVal(false);
    assertSame(false, $bool->value());
});

$runner->test('renders its value unchanged', function () {
    $true = new BoolVal(true);
    assertTrue($true->render(array()));
});

$runner->test('compiler tests: BoolVal', function () {
    $result = BoolVal::compile(true, test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame(true, $result->value()->value());
    assertSame('boolean', $result->value()->value_type());

    $result = BoolVal::compile(false, test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame(false, $result->value()->value());
    assertSame('boolean', $result->value()->value_type());
});

$runner->test('rejects null', function () {
    assert_compile_error(BoolVal::compile(null, test_schema(), 'null'), 'null: must be a boolean');
});

$runner->test('rejects integer', function () {
    assert_compile_error(BoolVal::compile(42, test_schema(), '42'), '42: must be a boolean');
});

$runner->test('rejects float', function () {
    assert_compile_error(BoolVal::compile(42.0, test_schema(), '42.0'), '42.0: must be a boolean');
});

$runner->test('rejects string', function () {
    assert_compile_error(BoolVal::compile('true', test_schema(), 'true'), 'true: must be a boolean');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(BoolVal::compile('', test_schema(), '""'), '"": must be a boolean');
});

$runner->test('rejects array', function () {
    assert_compile_error(BoolVal::compile(array(), test_schema(), 'array()'), 'array(): must be a boolean');
});

$runner->finish();