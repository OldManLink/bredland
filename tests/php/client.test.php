#!/usr/bin/env php
<?php
require getenv('TEST_CONFIG');
require __DIR__ . '/lib/testlib.php';
$repoRoot = dirname(dirname(__DIR__));
require $repoRoot . '/templates/noc/lib/compatibility.php';
require $repoRoot . '/templates/noc/lib/client.php';

$exports = require $repoRoot . '/templates/noc/lib/exports.php';

$clientsDir = $repoRoot . '/templates/noc/clients';

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

assertTrue(is_known_value_type('string'));
assertTrue(is_known_value_type('integer'));
assertFalse(is_known_value_type('array'));
