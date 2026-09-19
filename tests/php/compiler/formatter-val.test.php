#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/formatter-val.php';

$runner = new TestSuiteRunner('formatter-val');

$runner->test('compiles field without formatter', function () {
    $result = FormatterVal::compile(
        array(
            'field' => 'uptime'
        ),
        test_schema(),
        'FormatterVal'
    );

    assert_compile_success($result);

    $formatterVal = $result->value();

    assertSame('uptime', $formatterVal->field()->value());
    assertSame(array(), $formatterVal->formatter()->values());
});

$runner->test('compiles field with formatter', function () {
    $result = FormatterVal::compile(
        array(
            'field' => 'uptime',
            'formatter' => 'display_duration'
        ),
        test_schema(),
        'FormatterVal'
    );

    assert_compile_success($result);

    $formatterVal = $result->value();

    assertSame('uptime', $formatterVal->field()->value());
    assertSame(
        array('display_duration'),
        array_map(
            function ($formatter) {
                return $formatter->name();
            },
            $formatterVal->formatter()->values()
        )
    );
});

$runner->test('rejects formatter incompatible with field type', function () {
    $result = FormatterVal::compile(
        array(
            'field' => 'temperature',
            'formatter' => 'display_duration'
        ),
        test_schema(),
        'FormatterVal'
    );

    assert_compile_error(
        $result,
        'FormatterVal.display_duration: incompatible with float'
    );
});

$runner->test('renders unformatted field value', function () {
    $result = FormatterVal::compile(
        array(
            'field' => 'uptime'
        ),
        test_schema(),
        'FormatterVal'
    );

    assert_compile_success($result);

    assertSame(
        42,
        $result->value()->render(
            array(
                'uptime' => 42
            )
        )
    );
});

$runner->test('renders formatted field value', function () {
    $result = FormatterVal::compile(
        array(
            'field' => 'uptime',
            'formatter' => 'display_duration'
        ),
        test_schema(),
        'FormatterVal'
    );

    assert_compile_success($result);

    assertSame(
        display_duration(420),
        $result->value()->render(
            array(
                'uptime' => 420
            )
        )
    );
});

$runner->test('renders unavailable when formatted field runtime type does not match schema', function () {
    $result = FormatterVal::compile(
        array(
            'field' => 'uptime',
            'formatter' => 'display_duration'
        ),
        test_schema(),
        'FormatterVal'
    );

    assert_compile_success($result);

    assertSame(
        'unavailable',
        $result->value()->render(
            array(
                'uptime' => 'banana'
            )
        )
    );
});

$runner->finish();
