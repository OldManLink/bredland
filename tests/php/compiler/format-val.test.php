#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$libRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib';
require_once $libRoot .'/formatters.php';
$compilerRoot = $libRoot . '/compiler';
require_once $compilerRoot .'/format-val.php';

$runner = new TestSuiteRunner('FormatVal');

$runner->test('instance creation', function () {
    $format = new FormatVal('display_uptime', array('integer' => true));

    assertSame('display_uptime', $format->name());
    assertSame(array('integer' => true), $format->value_types());
});

$runner->test('renders display_uptime formatter', function () {
    $format = new FormatVal('display_uptime', array('integer' => true));
    $formatter = $format->render();
    assertSame(display_uptime(420),$formatter(420));
});

$runner->test('renders display_memory formatter', function () {
    $format = new FormatVal('display_memory', array('integer' => true));
    $formatter = $format->render();
    assertSame(display_memory(420420420), $formatter(420420420));
});

$runner->test('compiler tests: FormatVal', function () {
    $result = FormatVal::compile('display_uptime', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('display_uptime', $result->value()->name());
    assertSame(array('integer' => true), $result->value()->value_types());

    assert_compile_error(FormatVal::compile('no_such_format', test_schema(), 'format'), 'format: no_such_format must exist in exports');
});

$runner->test('rejects null', function () {
    assert_compile_error(FormatVal::compile(null, test_schema(), 'null'), 'null.function: must be a non-empty string');
});

$runner->test('rejects boolean', function () {
    assert_compile_error(FormatVal::compile(true, test_schema(), 'true'), 'true.function: must be a non-empty string');
});

$runner->test('rejects integer', function () {
    assert_compile_error(FormatVal::compile(42, test_schema(), '42'), '42.function: must be a non-empty string');
});

$runner->test('rejects float', function () {
    assert_compile_error(FormatVal::compile(42.0, test_schema(), '42.0'), '42.0.function: must be a non-empty string');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(FormatVal::compile('', test_schema(), '""'), '"".function: must be a non-empty string');
});

$runner->test('rejects array', function () {
    assert_compile_error(FormatVal::compile(array(), test_schema(), 'array()'), 'array().function: must be a non-empty string');
});

$runner->finish();