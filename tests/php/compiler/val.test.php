#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/val.php';

$runner = new TestRunner('Val');

$runner->test('instance creation', function () {
     assertThrows(
         'InstantiationException',
         'Programming error: Val cannot be instantiated: 42',
         function () {
             new Val(42);
         }
     );
});

$runner->test('compiles boolean as BoolVal', function () {
    $result = Val::compile(true, test_schema(), 'Happy BoolVal Path');
    assert_compile_success($result);
    assertTrue(get_class($result->value()) === 'BoolVal', 'BoolVal expected');
    assertTrue($result->value()->value() === true, 'true expected');
});

$runner->test('compiles integer as IntVal', function () {
    $result = Val::compile(42, test_schema(), 'Happy IntVal Path');
    assert_compile_success($result);
    assertTrue(get_class($result->value()) === 'IntVal', 'IntVal expected');
    assertTrue($result->value()->value() === 42, '42 expected');
});

$runner->test('compiles float as FloatVal', function () {
    $result = Val::compile(42.0, test_schema(), 'Happy FloatVal Path');
    assert_compile_success($result);
    assertTrue(get_class($result->value()) === 'FloatVal', 'FloatVal expected');
    assertTrue($result->value()->value() === 42.0, '42.0 expected');
});

$runner->test('compiles string as StrVal', function () {
    $result = Val::compile('42', test_schema(), 'Happy StrVal Path');
    assert_compile_success($result);
    assertTrue(get_class($result->value()) === 'StrVal', 'StrVal expected');
    assertTrue($result->value()->value() === '42', "'42' expected");
});

$runner->test('compiles field reference as FieldVal', function () {
    $result = Val::compile(array('field' => 'version'), test_schema(), 'Happy FieldVal Path');
    assert_compile_success($result);
    assertTrue(get_class($result->value()) === 'FieldVal', 'FieldVal expected');
    assertSame('version', $result->value()->value());
});

$runner->test('rejects null', function () {
    assert_compile_error( Val::compile(null, test_schema(), 'null'), 'null: must not be undefined');
});

$runner->test('rejects array value type', function () {
    assert_compile_error(Val::compile(array(), test_schema(), 'array()'), 'array(): unsupported value_type: array' );
});

$runner->finish();