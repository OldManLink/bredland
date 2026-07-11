#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/client.php';

$exports = require_once $nocRoot . '/lib/exports.php';
$clientsDir = $nocRoot . '/clients';

$client = read_client_file($clientsDir . '/mikrotik.json');

assertSame('mikrotik', $client['host']);
assertSame('MikroTik', $client['title']);
assertSame(10, $client['order']);
assertSame(2, count($client['fields']));

$tmp = tempnam(sys_get_temp_dir(), 'client');
file_put_contents($tmp, '{ this is not json');

assertSame(null, read_client_file($tmp));

unlink($tmp);

$clientsDir = sys_get_temp_dir() . '/clients-' . uniqid();
$dataDir = sys_get_temp_dir() . '/data-' . uniqid();

mkdir($clientsDir);
mkdir($dataDir);

file_put_contents($clientsDir . '/bad.json', '{ this is not json');

$clients = load_clients($clientsDir, $dataDir);

assertSame(0, count($clients), 'Malformed client definitions should be skipped');

unlink($clientsDir . '/bad.json');
rmdir($clientsDir);
rmdir($dataDir);

$clientsDir = sys_get_temp_dir() . '/clients-' . uniqid();
$dataDir = sys_get_temp_dir() . '/data-' . uniqid();

mkdir($clientsDir);
mkdir($dataDir);

file_put_contents(
    $clientsDir . '/bredland.json',
    json_encode(array(
        'host' => 'bredland',
        'title' => 'Bredland',
        'order' => 20,
        'fields' => array(
            array(
                'label' => 'Uptime',
                'field' => 'uptime',
                'valueType' => 'integer',
                'format' => 'display_uptime',
            ),
        ),
    ))
);

$dataFile = daily_jsonl_filename($dataDir, 'bredland', gmdate('Y-m-d'));

file_put_contents(
    $dataFile,
    json_encode(array(
        'schema' => 1,
        'ts' => gmdate('Y-m-d\TH:i:s\Z'),
        'host' => 'bredland',
        'uptime' => 123,
    )) . "\n"
);

$clients = load_clients($clientsDir, $dataDir);

assertSame(1, count($clients));
assertSame('bredland', $clients[0]['host']);
assertSame('Bredland', $clients[0]['title']);
assertSame(123, $clients[0]['heartbeat']['uptime']);
assertSame($dataFile, $clients[0]['heartbeat_file']);
assertTrue(is_int($clients[0]['age']));

unlink($clientsDir . '/bredland.json');
unlink($dataFile);
rmdir($clientsDir);
rmdir($dataDir);

$a = array('order' => 10);
$b = array('order' => 20);

assertTrue(compare_client_order($a, $b) < 0);
assertTrue(compare_client_order($b, $a) > 0);
assertSame(0, compare_client_order($a, $a));

$client = array(
    'heartbeat' => array(
        'uptime' => 1165727,
        'old_uptime' => '1w6d11:48:47',
        'free_memory' => 1073741824,
        'version' => '7.23.1 (stable)',
    ),
);

$field = array(
    'label' => 'Uptime',
    'field' => 'uptime',
    'valueType' => 'integer',
    'format' => 'display_uptime',
);

assertSame('1w6d11:48:47', display_client_field($client, $field));

$field = array(
    'label' => 'Uptime',
    'field' => 'old_uptime',
    'valueType' => 'integer',
    'format' => 'display_uptime',
);

assertSame('unavailable', display_client_field($client, $field));

$field = array(
    'label' => 'Uptime',
    'field' => 'old_uptime',
    'valueType' => 'string',
);

assertSame('1w6d11:48:47', display_client_field($client, $field));

$field = array(
    'field' => 'uptime',
    'format' => 'display_uptime',
);

assertSame('unavailable', display_client_field($client, $field));

$field = array(
    'label' => 'Free memory',
    'field' => 'free_memory',
    'valueType' => 'integer',
    'format' => 'display_memory',
);

assertSame('1.0 GiB', display_client_field($client, $field));

$field = array(
    'label' => 'OS Version',
    'field' => 'version',
    'valueType' => 'string',
);

assertSame('7.23.1 (stable)', display_client_field($client, $field));

$field = array(
    'label' => 'Hacker',
    'field' => 'missing',
    'valueType' => 'array',
    'format' => 'inject_vulnerablility',
);

assertSame('unavailable', display_client_field($client, $field));

assertTrue(is_known_value_type('integer'));
assertTrue(is_known_value_type('float'));
assertTrue(is_known_value_type('string'));
assertTrue(is_known_value_type('boolean'));
assertFalse(is_known_value_type('array'));

assertTrue(value_matches_type(123, 'integer'));
assertFalse(value_matches_type('123', 'integer'));

assertTrue(value_matches_type(12.3, 'float'));
assertTrue(value_matches_type(.123, 'float'));
assertFalse(value_matches_type('123', 'float'));

assertTrue(value_matches_type(true, 'boolean'));
assertTrue(value_matches_type(false, 'boolean'));
assertFalse(value_matches_type('true', 'boolean'));
assertFalse(value_matches_type('false', 'boolean'));
assertFalse(value_matches_type(1, 'boolean'));
assertFalse(value_matches_type(0, 'boolean'));
assertFalse(value_matches_type(null, 'boolean'));

assertTrue(value_matches_type('123', 'string'));
assertFalse(value_matches_type(123, 'string'));
