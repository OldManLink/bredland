#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpRoot = dirname(__DIR__);
require_once $phpRoot . '/lib/testlib.php';
$libRoot = dirname(dirname($phpRoot)) . '/templates/noc/lib';
require_once $libRoot . '/formatters.php';
$compilerRoot = $libRoot . '/compiler';
require_once $compilerRoot . '/field.php';

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
    "label": "Uptime",
    "field": "ts",
    "format": "display_uptime"
}
JSON
);
$fieldJson3 = from_json(<<<'JSON'
{
    "label": "Uptime",
    "field": "temperature",
    "format": "display_uptime"
}
JSON
);

$runner = new TestRunner('Field');

$runner->test('instance creation', function () {
    $label = new StrVal('Uptime');
    $fieldVal = new FieldVal('uptime', 'integer');
    $format = new FormatVal('display_uptime', array('integer'));

    $field = new Field(
        $label,
        $fieldVal,
        $format
    );

    assertSame($label, $field->label());
    assertSame($fieldVal, $field->field());
    assertSame($format, $field->format());
});

$runner->test('renders formatted field value', function () {
    $field = new Field(
        new StrVal('Uptime'),
        new FieldVal('uptime', 'integer'),
        new FormatVal('display_uptime', array('integer' => true))
    );
    assertSame(display_uptime(1165727), $field->render(array('uptime' => 1165727)));
});

$runner->test('returns unformatted value when runtime type does not match', function () {
    $field = new Field(
        new StrVal('Uptime'),
        new FieldVal('uptime', 'integer'),
        new FormatVal('display_uptime', array('integer' => true))
    );

    assertSame('unavailable', $field->render(array('uptime' => 'not-an-integer')));
});

// Compiler tests
$runner->test('compiles field', function () use ($fieldJson) {
    $result = Field::compile($fieldJson, test_schema(), 'Happy Path');

    assert_compile_success($result);
    assertTrue($result->value()->label() instanceof StrVal);
    assertTrue($result->value()->field() instanceof FieldVal);
    assertTrue($result->value()->format() instanceof FormatVal);

    assertSame('Uptime', $result->value()->label()->value());
    assertSame('uptime', $result->value()->field()->value());
    assertSame('integer', $result->value()->value_type());
    assertSame('display_uptime', $result->value()->format()->name());
});

$runner->test('rejects non-object field', function () {
    assert_compile_error(Field::compile(42, test_schema(), '42'), '42: must be an object');
});


$runner->test('invalid identifier: fiéld', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime",
        "fiéld": "uptime",
        "format": "display_uptime"
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field: invalid identifier: fiéld');
});

$runner->test('missing label', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "field": "uptime",
        "format": "display_uptime"
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field: expected label');
});

$runner->test('missing field', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime",
        "format": "display_uptime"
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field: expected field');
});

$runner->test('missing format', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime",
        "field": "uptime"
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field: expected format');
});

$runner->test('non-existent format', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime",
        "field": "uptime",
        "format": "no_such_function"
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field.format: no_such_function must exist in exports');
});

$runner->test('unsupported attribute: size', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime",
        "field": "uptime",
        "format": "display_uptime",
        "size": "42"
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field: unsupported attribute: size');
});

$runner->finish();