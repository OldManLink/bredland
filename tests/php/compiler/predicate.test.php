#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/predicate.php';

$runner = new TestRunner('predicate');

$runner->test('instance creation', function () {
    $predicate = new Predicate(
        new FieldVal('update_available', 'boolean'),
        new OpVal('equals', array('string')),
        new BoolVal(true)
    );

    assertSame('update_available', $predicate->receiver()->value());
    assertSame('equals', $predicate->operator()->name());
    assertSame(true, $predicate->argument()->value());
});

$runner->test('renders true predicate', function () {
    $predicate = new Predicate(
        new FieldVal('update_available', 'boolean'),
        new OpVal('equals', array('boolean')),
        new BoolVal(true)
    );
    assertTrue($predicate->render(array('update_available' => true)));
});

$runner->test('renders false predicate', function () {
    $predicate = new Predicate(
        new FieldVal('update_available', 'boolean'),
        new OpVal('equals', array('boolean')),
        new BoolVal(true)
    );
    assertFalse($predicate->render(array('update_available' => false)));
});

// Compiler tests
$runner->test('compiles boolean predicate', function () {
    $schema = test_schema();

    $predicateJson = from_json(<<<'JSON'
    {
        "field": "update_available",
        "operator": "equals",
        "value": true
    }
JSON
    );

    $result = Predicate::compile($predicateJson, $schema, 'Happy Path');

    assert_compile_success($result);

    $predicate = $result->value();

    assertSame('update_available', $predicate->receiver()->value());
    assertSame('equals', $predicate->operator()->name());
    assertSame(true, $predicate->argument()->value());
});

$runner->test('compiles float predicate', function () {
    $schema = test_schema();

    $predicateJson = from_json(<<<'JSON'
    {
        "field": "temperature",
        "operator": "lessThan",
        "value": 42.5
    }
JSON
    );

    $result = Predicate::compile($predicateJson, $schema, 'Happy Path');

    assert_compile_success($result);

    $predicate = $result->value();

    assertSame('temperature', $predicate->receiver()->value());
    assertSame('lessThan', $predicate->operator()->name());
    assertSame(42.5, $predicate->argument()->value());
});

$runner->test('compiles field-valued predicate', function () {
    $schema = test_schema();

    $predicateJson = from_json(<<<'JSON'
    {
        "field": "latest_version",
        "operator": "versionGreaterThan",
        "value": {
            "field": "version"
        }
    }
JSON
    );

    $result = Predicate::compile($predicateJson, $schema, 'Happy Path');

    assert_compile_success($result);

    $predicate = $result->value();

    assertSame('latest_version', $predicate->receiver()->value());
    assertSame('versionGreaterThan', $predicate->operator()->name());

    $argument = $predicate->argument();
    assertTrue($argument instanceof FieldVal);
    assertSame('version', $argument->value());
    assertSame('string', $argument->value_type());
});

$runner->test('renders true field-valued predicate', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "latest_version",
        "operator": "versionGreaterThan",
        "value": {
            "field": "version"
        }
    }
JSON
    );

    $result = Predicate::compile($json, $schema, 'Happy Path');
    assert_compile_success($result);

    assertTrue(
            $result->value()->render(
                    array(
                            'latest_version' => '7.23.3',
                            'version' => '7.23.1'
                    )
            )
    );
});

$runner->test('renders false field-valued predicate', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "latest_version",
        "operator": "versionGreaterThan",
        "value": {
            "field": "version"
        }
    }
JSON
    );

    $result = Predicate::compile($json, $schema, 'Happy Path');
    assert_compile_success($result);

    assertFalse(
            $result->value()->render(
                    array(
                            'latest_version' => '7.23.1',
                            'version' => '7.23.3'
                    )
            )
    );
});

$runner->test('rejects field-valued predicate with incompatible type', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "latest_version",
        "operator": "versionGreaterThan",
        "value": {
            "field": "uptime"
        }
    }
JSON
    );

    assert_compile_error(
            Predicate::compile($json, $schema, 'rule.when'),
            'rule.when.versionGreaterThan: integer incompatible with string'
    );
});

$runner->test('rejects field-valued predicate with unknown field', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "latest_version",
        "operator": "versionGreaterThan",
        "value": {
            "field": "banana"
        }
    }
JSON
    );

    assert_compile_error(
            Predicate::compile($json, $schema, 'rule.when'),
            "rule.when.value.FieldVal: 'banana' must exist in schema"
    );
});

$runner->test('rejects malformed field-valued predicate', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "latest_version",
        "operator": "versionGreaterThan",
        "value": {
            "field": "version",
            "fubar": true
        }
    }
JSON
    );

    assert_compile_error(
            Predicate::compile($json, $schema, 'rule.when'),
            'rule.when.value: unsupported value_type: array'
    );
});

$runner->test('rejects missing field', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "operator": "equals",
        "value": true
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when: expected field'
    );
});

$runner->test('rejects unsupported attribute', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "feild": "",
        "operator": "equals",
        "value": true
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when: unsupported attribute: feild'
    );
});

$runner->test('rejects additional unsupported attribute', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "uptime",
        "operator": "equals",
        "fubar": [],
        "value": true
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when: unsupported attribute: fubar'
    );
});

$runner->test('rejects invalid attribute identifier', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "uptime",
        "operator": "equals",
        "fübar": [],
        "value": true
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when: invalid identifier: fübar'
    );
});

$runner->test('rejects empty field', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "",
        "operator": "equals",
        "value": true
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when.field: must be a non-empty string'
    );
});

$runner->test('rejects non-string numeric field', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": 42,
        "operator": "equals",
        "value": true
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when.field: must be a non-empty string'
    );
});

$runner->test('rejects non-string boolean field', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": false,
        "operator": "equals",
        "value": true
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when.field: must be a non-empty string'
    );
});

$runner->test('rejects undefined value', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "update_available",
        "operator": "equals",
        "value": null
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when.value: must not be undefined'
    );
});

$runner->test('rejects unsupported value type', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "update_available",
        "operator": "equals",
        "value": []
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when.value: unsupported value_type: array'
    );
});

$runner->test('rejects incompatible operator', function () {
    $schema = test_schema();

    $json = from_json(<<<'JSON'
    {
        "field": "update_available",
        "operator": "lessThan",
        "value": true
    }
JSON
    );

    assert_compile_error(
        Predicate::compile($json, $schema, 'rule.when'),
        'rule.when.lessThan: incompatible with boolean'
    );
});

$runner->finish();