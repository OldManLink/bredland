#!/usr/bin/env php
<?php
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/compatibility.php';

$runner = new TestSuiteRunner('compatibility');

$runner->test('telemetry_hash_equals', function () {
    assertTrue(telemetry_hash_equals('secret', 'secret'));
    assertFalse(telemetry_hash_equals('secret', 'wrong'));
    assertFalse(telemetry_hash_equals('secret', 'secret-extra'));
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

$runner->finish();
