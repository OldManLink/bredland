#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocLibRoot = dirname(dirname(__DIR__)) . '/templates/noc/lib';

require_once $nocLibRoot . '/formatters.php';
require_once $nocLibRoot . '/value-type.php';

$runner = new TestSuiteRunner('formatters');

$runner->test('formats memory values', function () {
    assertSame('1.0 KiB', display_memory(1024));
    assertSame('1.0 MiB', display_memory(1048576));
    assertSame('1.0 GiB', display_memory(1073741824));
});

$runner->test('formats uptime values', function () {
    assertSame('0s', display_uptime(0));
    assertSame('59s', display_uptime(59));
    assertSame('01:00', display_uptime(60));
    assertSame('1d00:00:00', display_uptime(86400));
    assertSame('1w6d11:48:47', display_uptime(1165727));
});

$runner->test('exports valid formatter definitions', function () {
    $exports = get_exports();

    assertTrue(
        isset($exports['formatters']),
        'formatters must be exported'
    );

    assertTrue(
        is_array($exports['formatters']),
        'formatter exports must be an array'
    );

    foreach ($exports['formatters'] as $formatter => $definition) {
        assertTrue(
            is_string($formatter),
            'formatter export names must be strings'
        );

        assertTrue(
            function_exists($formatter),
            "formatter function does not exist: $formatter"
        );

        assertTrue(
            isset($definition['value_types']),
            "formatter must define value_types: $formatter"
        );

        assertTrue(
            is_array($definition['value_types']),
            "formatter value_types must be an array: $formatter"
        );

        foreach ($definition['value_types'] as $valueType => $ignore) {
            assertTrue(
                ValueType::is_supported($valueType),
                "unsupported formatter value_type: $valueType"
            );
        }

        $reflection = new ReflectionFunction($formatter);

        assertSame(
            1,
            $reflection->getNumberOfParameters(),
            "formatter must accept exactly one parameter: $formatter"
        );
    }
});

$runner->finish();