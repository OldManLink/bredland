#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpRoot = dirname(__DIR__);
require_once $phpRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpRoot)) . '/templates/noc/lib/compiler';
require_once $compilerRoot .'/utils.php';
require_once $compilerRoot .'/compilation-result.php';

$runner = new TestRunner('utils');

$runner->test('creates indexed paths', function () {
    assertSame('fields[0]', indexed_path('fields', 0));
    assertSame('rules[3]', indexed_path('rules', 3));
});


$runner->test('returns runtime types', function () {
    assertSame('string', runtime_type('hello'));
    assertSame('integer', runtime_type(123));
    assertSame('float', runtime_type(1.5));
    assertSame('boolean', runtime_type(true));
});

$runner->test('normalises php double to float', function () {
    assertSame('float', runtime_type(1.0));
});

$runner->test('accepts allowed keys', function () {
    $result = check_allowed_keys(
        array('host' => 'bredland', 'title' => 'Bredland'),
        array('host' => StrVal::class, 'title' => StrVal::class),
        'Happy Path');
    assert_compile_success($result);
});

$runner->test('rejects unsupported keys', function () {
    $result = check_allowed_keys(array('unknown' => 'value'), array('host' => StrVal::class), 'client');
    assert_compile_error($result, "client: unsupported attribute: unknown");
});

$runner->test('rejects invalid identifiers', function () {
    $result = check_allowed_keys(array('bad-key' => 'value'), array('host' => StrVal::class),'client');
    assert_compile_error($result, "client: invalid identifier: bad-key");
});

$runner->test('rejects missing required keys', function () {
    $result = check_allowed_keys(array(),array('host' => StrVal::class), 'client');
    assert_compile_error($result, 'client: expected host');
});

$runner->finish();