#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpRoot = dirname(__DIR__);
require_once $phpRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot . '/field-list.php';

$fieldJson = from_json(<<<'JSON'
{
    "label": "Uptime",
    "field": "uptime",
    "format": "display_uptime"
}
JSON
);

$fieldJson2 = from_json(<<<'JSON'
{
    "label": "Timestamp",
    "field": "ts",
    "format": "display_uptime"
}
JSON
);

$fieldJson3 = from_json(<<<'JSON'
{
    "label": "Temperature",
    "field": "temperature",
    "format": "display_value"
}
JSON
);

$runner = new TestSuiteRunner('FieldList');

$runner->test('instance creation', function () {
    $field = new Field(
        new StrVal('Uptime'),
        new FieldVal('uptime', 'integer'),
        new FormatVal('display_uptime', array('integer' => true))
    );

    $fieldList = new FieldList(array(
        'uptime' => $field
    ));

    assertSame(
        array(
            'uptime' => $field
        ),
        $fieldList->fields()
    );
});

$runner->test('gets field by name', function () {
    $field = new Field(
        new StrVal('Uptime'),
        new FieldVal('uptime', 'integer'),
        new FormatVal('display_uptime', array('integer' => true))
    );

    $fieldList = new FieldList(array(
        'uptime' => $field
    ));

    assertSame(
        $field,
        $fieldList->get('uptime')
    );
});

// Compiler tests

$runner->test('compiles field list', function () use ($fieldJson) {
    $result = FieldList::compile(
        array($fieldJson),
        test_schema(),
        'Happy array path'
    );

    assert_compile_success($result);
    assertTrue($result->value() instanceof FieldList);
    assertSame(1, count($result->value()->fields()));
    assertTrue($result->value()->fields()['uptime'] instanceof Field);
});

$runner->test('compiles field list with multiple fields', function () use (
    $fieldJson,
    $fieldJson2,
    $fieldJson3
) {
    $result = FieldList::compile(
        array($fieldJson, $fieldJson2, $fieldJson3),
        test_schema(),
        'Happy array(3) path'
    );

    assert_compile_success($result);
    assertSame(3, count($result->value()->fields()));
    assertTrue($result->value()->fields()['temperature'] instanceof Field);
});

$runner->test('compiles empty field list', function () {
    $result = FieldList::compile(
        array(),
        test_schema(),
        'Empty array'
    );

    assert_compile_success($result);
    assertSame(array(), $result->value()->fields());
});

$runner->test('rejects non-array field list', function () {
    assert_compile_error(
        FieldList::compile(
            42,
            test_schema(),
            'Number 42'
        ),
        'Number 42: must be an array'
    );
});

$runner->test('rejects invalid field in field list', function () {
    assert_compile_error(
        FieldList::compile(
            array(42),
            test_schema(),
            'array(42)'
        ),
        'array(42)[0]: must be an object'
    );
});

$runner->test('preserves invalid field index in compiler error', function () use ($fieldJson, $fieldJson2) {
    $invalidFieldJson = from_json(<<<'JSON'
{
    "label": "Temperature",
    "field": "temperature",
    "format": "display_uptime"
}
JSON
    );

    assert_compile_error(
        FieldList::compile(
            array($invalidFieldJson),
            test_schema(),
            'Fields'
        ),
        'Fields[0].display_uptime: incompatible with float'
    );

    assert_compile_error(
        FieldList::compile(
            array($fieldJson, $invalidFieldJson),
            test_schema(),
            'Fields'
        ),
        'Fields[1].display_uptime: incompatible with float'
    );

    assert_compile_error(
        FieldList::compile(
            array($fieldJson, $fieldJson2, $invalidFieldJson),
            test_schema(),
            'Fields'
        ),
        'Fields[2].display_uptime: incompatible with float'
    );
});

$runner->test('rejects unknown field', function () {
    $fieldList = new FieldList(array());

     assertThrows(
         'InvalidArgumentException',
         'Field not found: fubar',
         function () use ($fieldList) {
             $fieldList->get('fubar');
         }
     );
});

$runner->test('rejects duplicate field', function () use ($fieldJson) {
    assert_compile_error(
        FieldList::compile(
            array($fieldJson, $fieldJson),
            test_schema(),
            'Fields'
        ),
        'Fields[1].field: duplicate field: uptime'
    );
});

$runner->finish();