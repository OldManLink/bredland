#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/field-val.php';

$runner = new TestRunner('FieldVal');

$runner->test('instance creation', function () {
    $ts = new FieldVal('ts');

    assertSame('ts', $ts->value());
});

$runner->test('renders field value from heartbeat', function () {
    $field = new FieldVal('latest_version');

    $heartbeat = array('latest_version' => '7.23.2');

    assertSame('7.23.2', $field->render($heartbeat));
});

$runner->test('renders unavailable when field is missing from heartbeat', function () {
    $field = new FieldVal('latest_version');

    assertSame('unavailable', $field->render(array()));
});

$runner->test('compiler tests: FieldVal', function () {
    $result = FieldVal::compile('ts', test_schema(), 'Happy Path');
    assert_compile_success($result);

    assertTrue($result->value()->value() === 'ts', "'ts' expected");
});

$runner->test('compiles wrapped field definition', function () {
    $result = FieldVal::compile(
        array(
            'field' => 'version'
        ),
        test_schema(),
        'Happy Wrapped FieldVal Path'
    );

    assert_compile_success($result);

    $field = $result->value();
    assertTrue(
        $field instanceof FieldVal,
        'FieldVal expected'
    );
    assertSame('version', $field->value());
});

$runner->test('rejects null', function () {
    assert_compile_error(FieldVal::compile(null, test_schema(), 'null'), 'null: must be a non-empty string');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(FieldVal::compile('', test_schema(), '""'), '"": must be a non-empty string');
});

$runner->test('rejects boolean value type', function () {
    assert_compile_error(FieldVal::compile(true, test_schema(), 'true'), 'true: must be a non-empty string');
});

$runner->test('rejects integer value type', function () {
    assert_compile_error(FieldVal::compile(42, test_schema(), '42'), '42: must be a non-empty string');
});

$runner->test('rejects float value type', function () {
    assert_compile_error(FieldVal::compile(42.0, test_schema(), '42.0'), '42.0: must be a non-empty string');
});

$runner->test('rejects array value type', function () {
    assert_compile_error(FieldVal::compile(array(), test_schema(), 'array()'), 'array(): must be a non-empty string');
});

$runner->finish();