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
    "value": {
        "field": "uptime",
        "formatter": "display_duration"
    }
}
JSON
);

$fieldJson2 = from_json(<<<'JSON'
{
    "label": "Uptime",
    "value": {
        "field": "ts",
        "formatter": "display_duration"
    }
}
JSON
);
$fieldJson3 = from_json(<<<'JSON'
{
    "label": "Uptime",
    "value": {
        "field": "temperature",
        "formatter": "display_duration"
    }
}
JSON
);

$runner = new TestSuiteRunner('Field');

$runner->test('instance creation', function () {
    $label = new StrVal('Uptime');
    $fieldVal = new FieldVal('uptime', 'integer');
    $format = new FormatVal(
        'display_duration',
        array('integer' => true)
    );

    $value = new FormatterVal(
        $fieldVal,
        Option::some($format)
    );

    $field = new Field(
        $label,
        $value
    );

    assertSame($label, $field->label());
    assertSame($value, $field->value());
    assertSame($fieldVal, $field->field());
});

// Compiler tests
$runner->test('compiles field', function () use ($fieldJson) {
    $result = Field::compile($fieldJson, test_schema(), 'Happy Path');

    assert_compile_success($result);
    assertTrue($result->value()->label() instanceof StrVal);
    assertTrue($result->value()->value() instanceof FormatterVal);

    assertSame('Uptime', $result->value()->label()->value());
    assertSame('uptime', $result->value()->field()->value());
    assertSame('integer', $result->value()->value_type());
});

$runner->test('rejects non-object field', function () {
    assert_compile_error(Field::compile(42, test_schema(), '42'), '42: must be an object');
});


$runner->test('invalid identifier: fiéld', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime",
        "value": {
            "fiéld": "uptime",
            "formatter": "display_duration"
        }
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field.value: invalid identifier: fiéld');
});

$runner->test('missing label', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "value": {
            "field": "uptime",
            "formatter": "display_duration"
        }
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field.label: missing required part');
});

$runner->test('missing value', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime"
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field.value: missing required part');
});

$runner->test('non-existent format', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime",
        "value": {
            "field": "temperature",
            "formatter": "no_such_function"
        }
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field.value.formatter: no_such_function must exist in exports');
});

$runner->test('unsupported attribute: size', function () {
    $invalidFieldJson = from_json(<<<'JSON'
    {
        "label": "Uptime",
        "value": {
            "field": "uptime",
            "formatter": "display_duration"
        },
        "size": "42"
    }
JSON
    );
    assert_compile_error(Field::compile($invalidFieldJson, test_schema(), 'Field'), 'Field: unsupported attribute: size');
});

$runner->finish();