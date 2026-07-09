#!/usr/bin/env php
<?php
require getenv('TEST_CONFIG');
require __DIR__ . '/lib/testlib.php';
$repoRoot = dirname(dirname(__DIR__));
require $repoRoot . '/templates/noc/lib/client.php';

$exports = require $repoRoot . '/templates/noc/lib/exports.php';

$clientsDir = $repoRoot . '/templates/noc/clients';
$fixturesDir = $repoRoot . '/tests/fixtures/heartbeats';

$clientFiles = glob($clientsDir . '/*.json');

assertTrue(count($clientFiles) > 0, 'Expected at least one client definition');

$seenHosts = array();
foreach ($clientFiles as $clientFile) {
    $client = read_client_file($clientFile);
    $host = required_string($client, 'host', $clientFile);
    assertIdentifier($host, "$clientFile field $host must be a valid identifier");
    $expectedHost = pathinfo($clientFile, PATHINFO_FILENAME);
    assertSame($expectedHost, $host, "$clientFile host must match filename");
    assertFalse(isset($seenHosts[$host]), "Duplicate host: $host");
    $seenHosts[$host] = true;

    assertTrue(isset($client['title']), "$clientFile must define title");
    assertTrue(isset($client['fields']) && is_array($client['fields']), "$clientFile must define fields array");

    $fixtureFile = $fixturesDir . '/' . $host . '.json';
    assertTrue(file_exists($fixtureFile), "Missing heartbeat fixture for $host");

    $heartbeat = read_client_file($fixtureFile);

    $definedFields = array();

    foreach ($client['fields'] as $fieldDef) {
        $fieldName = required_string($fieldDef, 'field', $clientFile);
        assertIdentifier($fieldName, "$clientFile field $fieldName must be a valid identifier");

        $definedFields[$fieldName] = true;

        assertTrue(isset($fieldDef['label']), "$clientFile field $fieldName must define label");
        $valueType = required_string($fieldDef, 'valueType', $clientFile);

        assertTrue(
            in_array($valueType, array('string', 'integer'), true),
            "$clientFile field $fieldName has unknown valueType: $valueType"
        );
        assertTrue(array_key_exists($fieldName, $heartbeat), "$clientFile field $fieldName not found in $fixtureFile");

        if (isset($fieldDef['format'])) {
            $format = required_string($fieldDef, 'format', $clientFile);
            $valueType = required_string($fieldDef, 'valueType', $clientFile);
            assertTrue(
                is_known_value_type($valueType),
                "$clientFile field $fieldName has unknown valueType: $valueType"
            );
            assertTrue(
                isset($exports['formatters'][$format]),
                "Unknown formatter: $format"
            );
            assertTrue(
                in_array($valueType, $exports['formatters'][$format]['valueTypes'], true),
                "Formatter $format is not compatible with valueType $valueType"
            );
        }
    }

    assertTrue(isset($definedFields['uptime']), "$clientFile must define mandatory uptime field");
    assertTrue(isset($client['order']), "$clientFile must define order");
    assertTrue(is_int($client['order']), "$clientFile order must be an integer");
}

function required_string($array, $key, $context)
{
    assertTrue(isset($array[$key]), "$context must define $key");
    assertTrue(is_string($array[$key]) && $array[$key] !== '', "$context $key must be a non-empty string");

    return $array[$key];
}
