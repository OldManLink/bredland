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

    assertSame('array', runtime_type(array()));
    assertSame('array', runtime_type(array('foo')));
    assertSame('array', runtime_type(array('foo', 'bar')));

    assertSame('object', runtime_type(array('field' => 'version')));
    assertSame('object', runtime_type(array(-1 => 'foo', 0 => 'bar')));
    assertSame('object', runtime_type(array(1 => 'foo', 2 => 'bar')));
    assertSame('object', runtime_type(array(0 => 'foo', 2 => 'bar')));
    assertSame('object', runtime_type(array(1 => 'bar', 0 => 'foo')));
});

$runner->test('normalises php double to float', function () {
    assertSame('float', runtime_type(1.0));
});

$runner->finish();
