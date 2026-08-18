#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot .'/health-val.php';

$runner = new TestSuiteRunner('HealthVal');

$runner->test('instance creation', function () {
    $healthy = new HealthVal('healthy');
    $warning = new HealthVal('warning');
    $critical = new HealthVal('critical');

    assertSame('healthy', $healthy->value());
    assertSame('warning', $warning->value());
    assertSame('critical', $critical->value());
});

$runner->test('renders its value unchanged', function () {
    $healthy = new HealthVal('healthy');
    assertSame('healthy', $healthy->render(array()));
});

$runner->test('compiler tests: HealthVal', function () {
    $result = HealthVal::compile('healthy', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('healthy', $result->value()->value());

    $result = HealthVal::compile('warning', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('warning', $result->value()->value());

    $result = HealthVal::compile('critical', test_schema(), 'Happy Path');
    assert_compile_success($result);
    assertSame('critical', $result->value()->value());

    assert_compile_error(HealthVal::compile('hungover', test_schema(), 'hungover'), 'hungover: unsupported health value: hungover');
});

$runner->test('rejects null', function () {
    assert_compile_error(HealthVal::compile(null, test_schema(), 'null'), 'null.health: must be a non-empty string');
});

$runner->test('rejects boolean', function () {
    assert_compile_error(HealthVal::compile(true, test_schema(), 'true'), 'true.health: must be a non-empty string');
});

$runner->test('rejects integer', function () {
    assert_compile_error(HealthVal::compile(42, test_schema(), '42'), '42.health: must be a non-empty string');
});

$runner->test('rejects float', function () {
    assert_compile_error(HealthVal::compile(42.0, test_schema(), '42.0'), '42.0.health: must be a non-empty string');
});

$runner->test('rejects empty string', function () {
    assert_compile_error(HealthVal::compile('', test_schema(), '""'), '"".health: must be a non-empty string');
});

$runner->test('rejects array', function () {
    assert_compile_error(HealthVal::compile(array(), test_schema(), 'array()'), 'array().health: must be a non-empty string');
});

$runner->finish();