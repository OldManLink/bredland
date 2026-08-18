#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';

require_once $nocRoot . '/lib/value-type.php';

$runner = new TestSuiteRunner('ValueType');

$runner->test('recognises supported value types', function () {
    assertTrue(ValueType::is_supported('boolean'));
    assertTrue(ValueType::is_supported('integer'));
    assertTrue(ValueType::is_supported('float'));
    assertTrue(ValueType::is_supported('string'));
});

$runner->test('rejects unsupported value type', function () {
    assertFalse(ValueType::is_supported('array'));
});

$runner->test('matches values of the same type', function () {
    assertTrue(ValueType::matches('boolean', true));
    assertTrue(ValueType::matches('integer', 42));
    assertTrue(ValueType::matches('float', 3.14));
    assertTrue(ValueType::matches('string', 'hello'));
});

$runner->test('rejects values of a different type', function () {
    assertFalse(ValueType::matches('boolean', 1));
    assertFalse(ValueType::matches('integer', '42'));
    assertFalse(ValueType::matches('string', 42));
});

$runner->test('float accepts integer values', function () {
    assertTrue(ValueType::matches('float', 42));
});

$runner->finish();
