#!/usr/bin/env php
<?php
require getenv('TEST_CONFIG');
require __DIR__ . '/lib/testlib.php';
$repoRoot = dirname(dirname(__DIR__));

$capabilities = require $repoRoot . '/templates/noc/lib/exports.php';

$clientsDir = $repoRoot . '/templates/noc/clients';
$fixturesDir = $repoRoot . '/tests/fixtures/heartbeats';

$clientFiles = glob($clientsDir . '/*.json');

assertTrue(count($clientFiles) > 0, 'Expected at least one client definition');

foreach ($clientFiles as $clientFile) {
    $client = read_json_file($clientFile);
    $host = required_string($client, 'host', $clientFile);

    assertTrue(isset($client['title']), "$clientFile must define title");
    assertTrue(isset($client['fields']) && is_array($client['fields']), "$clientFile must define fields array");

    $fixtureFile = $fixturesDir . '/' . $host . '.json';
    assertTrue(file_exists($fixtureFile), "Missing heartbeat fixture for $host");

    $heartbeat = read_json_file($fixtureFile);

    $definedFields = array();

    foreach ($client['fields'] as $fieldDef) {
        $fieldName = required_string($fieldDef, 'field', $clientFile);

        $definedFields[$fieldName] = true;

        assertTrue(isset($fieldDef['label']), "$clientFile field $fieldName must define label");
        assertTrue(array_key_exists($fieldName, $heartbeat), "$clientFile field $fieldName not found in $fixtureFile");

        if (isset($fieldDef['format'])) {
            $format = $fieldDef['format'];
            assertTrue(
                in_array($format, $capabilities['formatters'], true),
                "Unknown formatter: $format"
            );
        }
    }

    assertTrue(isset($definedFields['uptime']), "$clientFile must define mandatory uptime field");
    assertTrue(isset($client['order']), "$clientFile must define order");
}

function read_json_file($path)
{
    $contents = file_get_contents($path);
    assertTrue($contents !== false, "Unable to read $path");

    $data = json_decode($contents, true);
    assertTrue(json_last_error() === JSON_ERROR_NONE, "Invalid JSON in $path: " . json_last_error_msg());

    return $data;
}

function required_string($array, $key, $context)
{
    assertTrue(isset($array[$key]), "$context must define $key");
    assertTrue(is_string($array[$key]) && $array[$key] !== '', "$context $key must be a non-empty string");

    return $array[$key];
}
