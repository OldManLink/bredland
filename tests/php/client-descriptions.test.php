#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
$testsRoot = dirname(__DIR__);
$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/compiler/client.php';
require_once $nocRoot . '/lib/client.php';

$runner = new TestRunner('client-descriptions');

$runner->test('all client descriptions compile', function () use ($nocRoot, $testsRoot) {
    $clientsDir = $nocRoot . '/clients';
    $schemasDir = $nocRoot . '/schemas';
    $fixturesDir = $testsRoot . '/fixtures/heartbeats';

    $seenHosts = array();

    foreach (glob($clientsDir . '/*.json') as $clientFile) {
        $name = basename($clientFile);

        $schemaFile = $schemasDir . '/' . $name;
        $fixtureFile = $fixturesDir . '/' . $name;

        assertTrue(
            file_exists($schemaFile),
            "Missing schema: $schemaFile"
        );

        assertTrue(
            file_exists($fixtureFile),
            "Missing heartbeat fixture: $fixtureFile"
        );

        $clientJson = from_json_file($clientFile);
        $schema = from_json_file($schemaFile);
        $fixture = from_json_file($fixtureFile);

        $result = Client::compile(
            $clientJson,
            $schema,
            $clientFile
        );

        assert_compile_success($result);

        $client = $result->value();

        $host = $client->host()->value();

        assertSame(
            pathinfo($clientFile, PATHINFO_FILENAME),
            $host,
            "$clientFile host must match filename"
        );

        assertFalse(
            isset($seenHosts[$host]),
            "Duplicate host: $host"
        );

        $seenHosts[$host] = true;

        assertTrue($client->title() instanceof StrVal, "$clientFile.title StrVal expected");
        assertTrue($client->fields() instanceof FieldList, "$clientFile.fields FieldList expected");
        assertTrue($client->order() instanceof IntVal, "$clientFile.order IntVal expected");
        assertTrue($client->fields()->fields()['uptime'] !== null, "$clientFile must define uptime");

        foreach ($fixture as $field => $value) {
            assertTrue(
                isset($schema[$field]),
                "$fixtureFile field '$field' missing from schema"
            );
        }
    }
});

$runner->finish();