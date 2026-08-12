#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$repoRoot = dirname(dirname(__DIR__));

require_once $repoRoot . '/templates/noc/lib/compiler/type-val.php';

$schemasDir = $repoRoot . '/templates/noc/schemas';
$fixturesDir = $repoRoot . '/tests/fixtures/heartbeats';

$schemaFiles = glob($schemasDir . '/*.json');

assertTrue(count($schemaFiles) > 0, 'Expected at least one heartbeat schema');

foreach ($schemaFiles as $schemaFile) {
    $schema = from_json_file($schemaFile);

    $host = pathinfo($schemaFile, PATHINFO_FILENAME);
    $fixtureFile = $fixturesDir . '/' . $host . '.json';

    assertTrue(
        file_exists($fixtureFile),
        "Missing heartbeat fixture for $host"
    );

    $heartbeat = from_json_file($fixtureFile);

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

        $type_result = TypeVal::compile(
                $value_type,
                $schema,
                "$schemaFile field $fieldName"
        );

        assert_compile_success($type_result);

        $matches_type = $type_result->value()->render();

        assertTrue(
                $matches_type($heartbeat[$fieldName]),
                "$fixtureFile field $fieldName does not match value_type $value_type"
        );
    }
}