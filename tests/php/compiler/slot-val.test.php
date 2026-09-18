<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/slot-val.php';
require_once $nocRoot . '/lib/compatibility.php';

$runner = new TestSuiteRunner('SlotVal');

$runner->test('instance creation', function () {
    $strVal = new StrVal(null);
    $fieldVal = new FieldVal(null, null);
    $slotVal = new SlotVal(array($strVal, $fieldVal, $strVal));
    assertSame(runtime_type($slotVal->parts()), 'array');
    assertSame(3, count($slotVal->parts()), 'expected count = 3');
    assertSame($strVal, $slotVal->parts()[0]);
    assertSame($fieldVal, $slotVal->parts()[1]);
    assertSame($strVal, $slotVal->parts()[2]);
});

$runner->test('renders string-only slot', function () {
    $slotVal = new SlotVal(array(new StrVal('Software update available')));
    assertSame('Software update available', $slotVal->render(array()));
});

$runner->test('renders field-only slot', function () {
    $slotVal = new SlotVal(array(new FieldVal('latest_version', 'string')));
    assertSame('7.23.2', $slotVal->render(array('latest_version' => '7.23.2')));
});

$runner->test('renders mixed slot', function () {
    $slotVal = new SlotVal(array(new StrVal('RouterOS '), new FieldVal('latest_version', 'string'), new StrVal(' is available.')));
    assertSame('RouterOS 7.23.2 is available.', $slotVal->render(array('latest_version' => '7.23.2')));
});

$runner->test('renders unavailable for missing field', function () {
    $slotVal = new SlotVal(array(new StrVal('RouterOS '), new FieldVal('latest_version', 'string'), new StrVal(' is available.')));
    assertSame('RouterOS unavailable is available.', $slotVal->render(array()));
});

$runner->test('rejects non-array definition', function () {
    $result = SlotVal::compile(
        'Software update available',
        test_schema(),
        'SlotVal'
    );

    assert_compile_error(
        $result,
        'SlotVal: must be an array'
    );
});

$runner->test('compiles string-only slot', function () {
    $result = SlotVal::compile(
        array(
            'Software update available'
        ),
        test_schema(),
        'SlotVal'
    );

    assert_compile_success($result);

    $slotVal = $result->value();

    assertSame(1, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof StrVal);
    assertSame(
        'Software update available',
        $slotVal->parts()[0]->value()
    );
});

$runner->test('compiles field-only slot', function () {
    $result = SlotVal::compile(
        array(
            array(
                'field' => 'latest_version'
            )
        ),
        test_schema(),
        'SlotVal'
    );

    assert_compile_success($result);

    $slotVal = $result->value();

    assertSame(1, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof FieldVal);
    assertSame(
        'latest_version',
        $slotVal->parts()[0]->value()
    );
});

$runner->test('compiles mixed slot', function () {
    $result = SlotVal::compile(
        array(
            'RouterOS ',
            array(
                'field' => 'latest_version'
            ),
            ' is available.'
        ),
        test_schema(),
        'SlotVal'
    );

    assert_compile_success($result);

    $slotVal = $result->value();

    assertSame(3, count($slotVal->parts()));
    assertTrue($slotVal->parts()[0] instanceof StrVal);
    assertTrue($slotVal->parts()[1] instanceof FieldVal);
    assertTrue($slotVal->parts()[2] instanceof StrVal);
});

$runner->test('rejects unknown field', function () {
    $result = SlotVal::compile(
        array(
            array(
                'field' => 'no_such_field'
            )
        ),
        test_schema(),
        'SlotVal'
    );

    assert_compile_error(
        $result,
        "SlotVal[0].FieldVal: 'no_such_field' must exist in schema"
    );
});

$runner->finish();