#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/op-val.php';

$runner = new TestRunner('OpVal');

$runner->test('instance creation', function () {
    $op = new OpVal('equals', array('boolean' => true, 'integer' => true, 'float' => true, 'string' => true));
    assertSame('equals', $op->name());
    assertSame(array('boolean' => true, 'integer' => true, 'float' => true, 'string' => true), $op->operand_types());

    $op = new OpVal('lessThan', array('integer' => true, 'float' => true));
    assertSame('lessThan', $op->name());
    assertSame(array('integer' => true, 'float' => true), $op->operand_types());
});

$runner->test('renders equals operator', function () {
    $equals = (new OpVal('equals', array('boolean' => true, 'integer' => true, 'float' => true, 'string' => true)))->render();

    assertTrue($equals(42, 42));
    assertFalse($equals(42, 43));
    assertFalse($equals(42, '42'));
});

$runner->test('renders lessThan operator', function () {
    $lessThan = (new OpVal('lessThan', array('integer' => true, 'float' => true)))->render();

    assertTrue($lessThan(42, 43));
    assertFalse($lessThan(43, 42));
    assertFalse($lessThan(42, 42));
});

$runner->test('lessThan rejects operands of different types', function () {
    $lessThan = (new OpVal('lessThan', array('integer' => true, 'float' => true)))->render();

    assertThrows('Exception', "Programming error: 'lessThan' requires operands of the same type",
        function () use ($lessThan) {
            $lessThan(42, 43.0);
        }
    );
});

$runner->test('compiler tests: OpVal', function () {
    $result = OpVal::compile('equals', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $op = $result->value();
    assertSame('equals', $op->name());
    assertSame(array('boolean' => true, 'integer' => true, 'float' => true, 'string' => true), $op->operand_types());

    $result = OpVal::compile('lessThan', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $op = $result->value();
    assertSame('lessThan', $op->name());
    assertSame(array('integer' => true, 'float' => true), $op->operand_types());

    $result = OpVal::compile('versionGreaterThan', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $op = $result->value();
    assertSame('versionGreaterThan', $op->name());
    assertSame(array('string' => true), $op->operand_types());

    assert_compile_error(OpVal::compile('greaterThan', test_schema(), 'gt'), 'gt: unsupported operator: greaterThan');
});

$runner->test('renders versionGreaterThan operator', function () {
    $result = OpVal::compile(
        'versionGreaterThan',
        test_schema(),
        'versionGreaterThan'
    );

    assert_compile_success($result);

    $versionGreaterThan = $result->value()->render();

    $cases = array(
        array('7.23.3',  '7.23.1',      true),
        array('7.23.10', '7.23.9',      true),
        array('7.24',    '7.23.10',     true),
        array('7.23.1',  '7.23.1',      false),
        array('7.23.1',  '7.23.3',      false),
        array('7.21rc6', '7.21beta11',   true),
        array('7.21',    '7.21rc6',      true),
        array('7.21.1',  '7.21',         true),
        array('7.23rc4', '7.23beta5',    true),
        array('7.23',    '7.23rc4',      true),
        array('7.23.1',  '7.23',         true),
    );

    foreach ($cases as $case) {
        list($left, $right, $expected) = $case;

        assertSame(
            $expected,
            $versionGreaterThan($left, $right),
            "$left versionGreaterThan $right"
        );
    }
});

$runner->test('rejects null', function () {
    assert_compile_error(OpVal::compile(null, test_schema(), 'null'), 'null.operator: must be a non-empty string');
});

$runner->test('rejects boolean', function () {
    assert_compile_error(OpVal::compile(true, test_schema(), 'true'), 'true.operator: must be a non-empty string');
});

$runner->test('rejects integer', function () {
    assert_compile_error(OpVal::compile(42, test_schema(), '42'), '42.operator: must be a non-empty string');
});

$runner->test('rejects float', function () {
    assert_compile_error(OpVal::compile(42.0, test_schema(), '42.0'), '42.0.operator: must be a non-empty string');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(OpVal::compile('', test_schema(), '""'), '"".operator: must be a non-empty string');
});

$runner->test('rejects array', function () {
    assert_compile_error(OpVal::compile(array(), test_schema(), 'array()'), 'array().operator: must be a non-empty string');
});

$runner->finish();