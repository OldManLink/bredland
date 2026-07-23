#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$repoRoot = dirname(dirname(__DIR__));

require_once $repoRoot . '/templates/noc/lib/client.php';

$schemasDir = $repoRoot . '/templates/noc/schemas';
$fixturesDir = $repoRoot . '/tests/fixtures/heartbeats';

$schemaFiles = glob($schemasDir . '/*.json');

assertTrue(count($schemaFiles) > 0, 'Expected at least one heartbeat schema');

foreach ($schemaFiles as $schemaFile) {
    $schema = read_client_file($schemaFile);

    assertTrue($schema !== null, "Unable to load schema: $schemaFile");

    $host = pathinfo($schemaFile, PATHINFO_FILENAME);
    $fixtureFile = $fixturesDir . '/' . $host . '.json';

    assertTrue(
        file_exists($fixtureFile),
        "Missing heartbeat fixture for $host"
    );

    $heartbeat = read_client_file($fixtureFile);

    assertTrue($heartbeat !== null, "Unable to load fixture: $fixtureFile");

    $schemaFields = array_keys($schema);
    $heartbeatFields = array_keys($heartbeat);

    sort($schemaFields);
    sort($heartbeatFields);

    assertSame(
        $schemaFields,
        $heartbeatFields,
        "$fixtureFile fields must exactly match $schemaFile"
    );

    foreach ($schema as $fieldName => $rule) {
        assertIdentifier(
            $fieldName,
            "$schemaFile field $fieldName must be a valid identifier"
        );

        assertTrue(
            is_array($rule),
            "$schemaFile field $fieldName rule must be an object"
        );

        $hasConst = array_key_exists('const', $rule);
        $hasType = array_key_exists('value_type', $rule);

        assertTrue(
            $hasConst xor $hasType,
            "$schemaFile field $fieldName must define exactly one of const or value_type"
        );

        if ($hasConst) {
            assertSame(
                $rule['const'],
                $heartbeat[$fieldName],
                "$fixtureFile field $fieldName must match declared constant"
            );

            continue;
        }

        $value_type = $rule['value_type'];

        assertTrue(
            is_string($value_type),
            "$schemaFile field $fieldName type must be a string"
        );

        assertTrue(
            is_known_value_type($value_type),
            "$schemaFile field $fieldName has unknown value_type: $value_type"
        );

        assertTrue(
            value_matches_type($heartbeat[$fieldName], $value_type),
            "$fixtureFile field $fieldName does not match value_type $value_type"
        );
    }
}