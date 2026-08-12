#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/type-val.php';

$runner = new TestRunner('TypeVal');

$runner->test('instance creation', function () {
    $bool = new TypeVal('boolean');
    $int = new TypeVal('integer');
    $float = new TypeVal('float');
    $str = new TypeVal('string');

    assertSame('boolean', $bool->value());
    assertSame('integer', $int->value());
    assertSame('float', $float->value());
    assertSame('string', $str->value());
});

$runner->test('renders integer type predicate', function () {
    $type = new TypeVal('integer');
    $isInteger = $type->render();

    assertTrue($isInteger(42));
    assertFalse($isInteger('42'));
});

$runner->test('renders boolean type predicate', function () {
    $type = new TypeVal('boolean');
    $isBoolean = $type->render();

    assertTrue($isBoolean(true));
    assertFalse($isBoolean(1));
});

$runner->test('renders float type predicate', function () {
    $type = new TypeVal('float');
    $isFloat = $type->render();

    assertTrue($isFloat(42.0));
    assertFalse($isFloat('42.0'));
});

$runner->test('renders string type predicate', function () {
    $type = new TypeVal('string');
    $isString = $type->render();

    assertTrue($isString('42'));
    assertFalse($isString(42));
});

$runner->test('compiler tests: TypeVal', function () {
    $result = TypeVal::compile('boolean', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('boolean', $result->value()->value());

    $result = TypeVal::compile('float', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('float', $result->value()->value());

    $result = TypeVal::compile('integer', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('integer', $result->value()->value());

    $result = TypeVal::compile('string', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('string', $result->value()->value());


    assert_compile_error(TypeVal::compile('blob', test_schema(), 'blob'), 'blob: unsupported value_type: blob');
});

$runner->test('rejects null', function () {
    assert_compile_error(TypeVal::compile(null, test_schema(), 'null'), 'null.type: must be a non-empty string');
});

$runner->test('rejects boolean value type', function () {
    assert_compile_error(TypeVal::compile(true, test_schema(), 'true'), 'true.type: must be a non-empty string');
});

$runner->test('rejects integer value type', function () {
    assert_compile_error(TypeVal::compile(42, test_schema(), '42'), '42.type: must be a non-empty string');
});

$runner->test('rejects float value type', function () {
    assert_compile_error(TypeVal::compile(42.0, test_schema(), '42.0'), '42.0.type: must be a non-empty string');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(TypeVal::compile('', test_schema(), '""'), '"".type: must be a non-empty string');
});

$runner->test('rejects array value type', function () {
    assert_compile_error(TypeVal::compile(array(), test_schema(), 'array()'), 'array().type: must be a non-empty string');
});

$runner->finish();