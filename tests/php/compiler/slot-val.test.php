<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/slot-val.php';

$runner = new TestRunner('SlotVal');

$runner->test('instance creation', function () {
    $strVal = new StrVal(null);
    $fieldVal = new FieldVal(null);
    $slotVal = new SlotVal(array($strVal, $fieldVal, $strVal));
    assertSame(runtime_type($slotVal->parts()), 'array');
    assertSame(3, count($slotVal->parts()), 'expected count = 3');
    assertSame($strVal, $slotVal->parts()[0]);
    assertSame($fieldVal, $slotVal->parts()[1]);
    assertSame($strVal, $slotVal->parts()[2]);
});

$runner->test('compiler tests: no placeholder', function () {
    $result = SlotVal::compile('Software update available', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $slotVal = $result->value();
    assertSame(1, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof StrVal);
    assertSame('Software update available', $slotVal->parts()[0]->value());
});

$runner->test('compiler tests: only placeholder', function () {
    $result = SlotVal::compile('{{latest_version}}', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $slotVal = $result->value();
    assertSame(1, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof FieldVal);
    assertSame('latest_version', $slotVal->parts()[0]->value());
});

$runner->test('compiler tests: one placeholder at the start', function () {
    $result = SlotVal::compile('{{latest_version}} update available.', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $slotVal = $result->value();
    assertSame(2, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof FieldVal);
    assertTrue($slotVal->parts()[1] instanceof StrVal);
    assertSame('latest_version', $slotVal->parts()[0]->value());
    assertSame(' update available.', $slotVal->parts()[1]->value());
});

$runner->test('compiler tests: one placeholder in the middle', function () {
    $result = SlotVal::compile('Update: {{latest_version}} available.', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $slotVal = $result->value();
    assertSame(3, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof StrVal);
    assertTrue($slotVal->parts()[1] instanceof FieldVal);
    assertTrue($slotVal->parts()[0] instanceof StrVal);
    assertSame('Update: ', $slotVal->parts()[0]->value());
    assertSame('latest_version', $slotVal->parts()[1]->value());
    assertSame(' available.', $slotVal->parts()[2]->value());
});

$runner->test('compiler tests: one placeholder at the end', function () {
    $result = SlotVal::compile('Update available: {{latest_version}}', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $slotVal = $result->value();
    assertSame(2, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof StrVal);
    assertTrue($slotVal->parts()[1] instanceof FieldVal);
    assertSame('Update available: ', $slotVal->parts()[0]->value());
    assertSame('latest_version', $slotVal->parts()[1]->value());
});

$runner->test('compiler tests: two placeholders', function () {
    $result = SlotVal::compile('Update version: {{latest_version}}, ts: {{ts}} available.', test_schema(), 'Happy Path');
    assert_compile_success($result);
    $slotVal = $result->value();
    assertSame(5, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof StrVal);
    assertTrue($slotVal->parts()[1] instanceof FieldVal);
    assertTrue($slotVal->parts()[2] instanceof StrVal);
    assertTrue($slotVal->parts()[3] instanceof FieldVal);
    assertTrue($slotVal->parts()[4] instanceof StrVal);
    assertSame('Update version: ', $slotVal->parts()[0]->value());
    assertSame('latest_version', $slotVal->parts()[1]->value());
    assertSame(', ts: ', $slotVal->parts()[2]->value());
    assertSame('ts', $slotVal->parts()[3]->value());
    assertSame(' available.', $slotVal->parts()[4]->value());
});

$runner->test('rejects invalid placeholder', function () {
    $result = SlotVal::compile('{{no_such_field}}', test_schema(), 'SlotVal');
    assert_compile_error($result, "SlotVal[0]: 'no_such_field' must exist in schema");
});

$runner->finish();