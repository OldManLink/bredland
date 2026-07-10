#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
$nocLibRoot = dirname(dirname(__DIR__)) . '/templates/noc/lib';
require_once $nocLibRoot . '/client.php';

$exports = require_once $nocLibRoot . '/exports.php';

assertTrue(isset($exports['formatters']), 'exports.php must define formatters');
assertTrue(is_array($exports['formatters']), 'exports.php formatters must be an array');

foreach ($exports['formatters'] as $formatter => $definition) {
    assertTrue(is_string($formatter), 'Formatter export names must be strings');
    assertTrue(function_exists($formatter), "Formatter function does not exist: $formatter");

    assertTrue(isset($definition['valueTypes']), "Formatter must define valueTypes: $formatter");
    assertTrue(is_array($definition['valueTypes']), "Formatter valueTypes must be an array: $formatter");
    foreach ($definition['valueTypes'] as $valueType) {
            assertTrue(is_known_value_type($valueType), "Formatter $formatter references unknown valueType: $valueType");
        }
    $reflection = new ReflectionFunction($formatter);
    assertSame(1, $reflection->getNumberOfParameters(), "Formatter must accept exactly 1 parameter: $formatter");
}
