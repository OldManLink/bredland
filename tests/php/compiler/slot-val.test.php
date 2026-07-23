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

$runner->test('compiler tests', function () {
    $schema = test_schema();
    $result = SlotVal::compile('Software update available', $schema, 'Happy Path');
    assert_compile_success($result);
    $slotVal = $result->value();
    assertSame(1, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof StrVal);
    assertSame('Software update available', $slotVal->parts()[0]->value());

    $result = SlotVal::compile('{{latest_version}}', $schema, 'Happy Path');
    assert_compile_success($result);
    $slotVal = $result->value();
    assertSame(1, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof FieldVal);
    assertSame('latest_version', $slotVal->parts()[0]->value());

    $result = SlotVal::compile('{{no_such_field}}', $schema, 'SlotVal');
    assert_compile_error($result, "SlotVal[0]: 'no_such_field' must exist in schema");
});

$runner->finish();