#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/client.php';
require_once $nocRoot . '/lib/compiler/client.php';
require_once $nocRoot . '/lib/exports.php';

$exports = get_exports();
$clientsDir = $nocRoot . '/clients';
$schemasDir = $nocRoot . '/schemas';

$clientFiles = glob($clientsDir . '/*.json');

assertTrue(count($clientFiles) > 0, 'Expected at least one client definition');

$seenHosts = array();
foreach ($clientFiles as $clientFile) {
    $schemaFile = $schemasDir . '/' . basename($clientFile);
    assertTrue(file_exists($schemaFile), "Missing heartbeat schema for $clientFile");
    $schema = read_client_file($schemaFile);
    assertTrue($schema !== null, "Unable to load heartbeat schema for $clientFile");

    $clientResult = Client::compile(read_client_file($clientFile), $schema, 'Test');

    $clientCompileErrors = implode("::", $clientResult->errors());
    assertTrue($clientResult->isSuccess(), "$clientFile: $clientCompileErrors");
    $client = $clientResult->value();
    $host = $client->host()->value();
    $expectedHost = pathinfo($clientFile, PATHINFO_FILENAME);
    assertSame($expectedHost, $host, "$clientFile host must match filename");
    assertFalse(isset($seenHosts[$host]), "Duplicate host: $host");
    $seenHosts[$host] = true;

    assertTrue($client->title() !== NULL, "$clientFile must define title");
    assertTrue(is_array($client->fields()), "$clientFile must define fields array");

    $definedFields = $client->fields();
    $foundFields = array();
    foreach($definedFields as $field) {
        $foundFields[$field->field()->value()] = true;
    }

    assertTrue(isset($foundFields['uptime']), "$clientFile must define mandatory uptime field");
    assertTrue($client->order() !== NULL, "$clientFile must define order");
}

