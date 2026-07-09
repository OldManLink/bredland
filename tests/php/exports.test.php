#!/usr/bin/env php
<?php
require getenv('TEST_CONFIG');
require __DIR__ . '/lib/testlib.php';
$repoRoot = dirname(dirname(__DIR__));
require $repoRoot . '/templates/noc/lib/telemetry.php';
require $repoRoot . '/templates/noc/lib/actions.php';

$exports = require $repoRoot . '/templates/noc/lib/exports.php';

assertTrue(isset($exports['formatters']), 'exports.php must define formatters');
assertTrue(is_array($exports['formatters']), 'exports.php formatters must be an array');

foreach ($exports['formatters'] as $formatter) {
    assertTrue(is_string($formatter), 'Formatter export names must be strings');
    assertTrue(function_exists($formatter), "Formatter function does not exist: $formatter");

    $reflection = new ReflectionFunction($formatter);
    assertSame(1, $reflection->getNumberOfParameters(), "Formatter must accept exactly one parameter: $formatter");
}

assertTrue(isset($exports['clickActions']), 'exports.php must define clickActions');
assertTrue(is_array($exports['clickActions']), 'exports.php clickActions must be an array');

foreach ($exports['clickActions'] as $clickAction) {
    assertTrue(is_string($clickAction), 'Click action export names must be strings');
    assertTrue(function_exists($clickAction), "Click action function does not exist: $clickAction");

    $reflection = new ReflectionFunction($clickAction);
    assertSame(1, $reflection->getNumberOfParameters(), "Click action must accept exactly one parameter: $clickAction");
}