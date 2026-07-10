#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/client.php';

$exports = require_once $nocRoot . '/lib/exports.php';
$clientsDir = $nocRoot . '/clients';
$schemasDir = $nocRoot . '/schemas';

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

    $schemaFile = $schemasDir . '/' . $host . '.json';
    assertTrue(file_exists($schemaFile), "Missing heartbeat schema for $host");

    $schema = read_client_file($schemaFile);
    assertTrue($schema !== null, "Unable to load heartbeat schema for $host");

    assertTrue(isset($client['title']), "$clientFile must define title");
    assertTrue(isset($client['fields']) && is_array($client['fields']), "$clientFile must define fields array");

    $definedFields = array();

    foreach ($client['fields'] as $fieldDef) {
        $fieldName = required_string($fieldDef, 'field', $clientFile);
        assertIdentifier($fieldName, "$clientFile field $fieldName must be a valid identifier");

        $definedFields[$fieldName] = true;

        assertTrue(isset($fieldDef['label']), "$clientFile field $fieldName must define label");

        $valueType = required_string($fieldDef, 'valueType', $clientFile);

        assertTrue(
            is_known_value_type($valueType),
            "$clientFile field $fieldName has unknown valueType: $valueType"
        );

        assertTrue(
            array_key_exists($fieldName, $schema),
            "$clientFile field $fieldName is not declared in $schemaFile"
        );

        assertTrue(
            isset($schema[$fieldName]['valueType']),
            "$clientFile field $fieldName refers to a constant schema field"
        );

        assertSame(
            $schema[$fieldName]['valueType'],
            $valueType,
            "$clientFile field $fieldName valueType must match $schemaFile"
        );

        if (isset($fieldDef['format'])) {
            $format = required_string($fieldDef, 'format', $clientFile);

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

