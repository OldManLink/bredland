#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$repoRoot = dirname(dirname(__DIR__));
$nocRoot = $repoRoot . '/templates/noc';

require_once $nocRoot . '/lib/compiler/client.php';
require_once $nocRoot . '/lib/client-loader.php';

$runner = new TestSuiteRunner('client-loader');

function copy_json_files($source_dir, $target_dir) {
    foreach (glob($source_dir . '/*.json') as $source_file) {
        $target_file = $target_dir . '/' . basename($source_file);

        if (!copy($source_file, $target_file)) {
            throw new RuntimeException('failed to copy fixture: ' . $source_file);
        }
    }
}

function write_heartbeat_jsonl_files($fixture_dir, $data_dir) {
    foreach (glob($fixture_dir . '/*.json') as $fixture_file) {
        $heartbeat = json_decode(file_get_contents($fixture_file), true);

        if (!is_array($heartbeat)) {
            throw new RuntimeException('invalid heartbeat fixture: ' . $fixture_file);
        }

        $host = $heartbeat['host'];
        $date = substr($heartbeat['ts'], 0, 10);
        $target_file = $data_dir . '/' . $host . '-' . $date . '.jsonl';

        if (file_put_contents(
            $target_file,
            json_encode($heartbeat, JSON_UNESCAPED_SLASHES) . "\n",
            LOCK_EX
        ) === false) {
            throw new RuntimeException(
                'failed to write heartbeat fixture: ' . $target_file
            );
        }
    }
}

function create_client_loader_fixture($repoRoot) {
    $root = sys_get_temp_dir() . '/client-loader-test-' . uniqid('', true);
    $clients_dir = $root . '/clients';
    $schemas_dir = $root . '/schemas';
    $data_dir = $root . '/data';

    mkdir($root);
    mkdir($clients_dir);
    mkdir($schemas_dir);
    mkdir($data_dir);

    copy_json_files($repoRoot . '/templates/noc/clients', $clients_dir);
    copy_json_files($repoRoot . '/templates/noc/schemas', $schemas_dir);
    write_heartbeat_jsonl_files(
        $repoRoot . '/tests/fixtures/heartbeats',
        $data_dir
    );

    return array(
        'root' => $root,
        'clients' => $clients_dir,
        'schemas' => $schemas_dir,
        'data' => $data_dir
    );
}

function remove_tree($path) {
    if (!file_exists($path)) {
        return;
    }

    if (is_dir($path)) {
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                remove_tree($path . '/' . $entry);
            }
        }

        rmdir($path);
        return;
    }

    unlink($path);
}

function read_json_object($file) {
    $value = json_decode(file_get_contents($file), true);

    if (!is_array($value)) {
        throw new RuntimeException('invalid JSON: ' . $file);
    }

    return $value;
}

function heartbeat_file_for_host($data_dir, $host) {
    $files = glob($data_dir . '/' . $host . '-*.jsonl');

    if (count($files) !== 1) {
        throw new RuntimeException(
            'expected exactly one heartbeat file for ' . $host
        );
    }

    return $files[0];
}

function client_titles($clients) {
    $titles = array();

    foreach ($clients as $client) {
        $titles[] = $client->get_title();
    }

    return $titles;
}

function client_for_host($clients, $host) {
    foreach ($clients as $client) {
        if ($client->host()->value() === $host) {
            return $client;
        }
    }

    throw new RuntimeException('Client not found: ' . $host);
}

$fixtureNow = trim(
    file_get_contents($repoRoot . '/tests/fixtures/last-fetched.timestamp')
);
$previousNow = getenv('NOC_NOW');
putenv('NOC_NOW=' . $fixtureNow);

$runner->test('load() returns fully formed clients from production-shaped fixtures', function () use ($repoRoot, $fixtureNow) {
    $fixture = create_client_loader_fixture($repoRoot);

    try {
        $clients = ClientLoader::load(
            $fixture['clients'],
            $fixture['schemas'],
            $fixture['data']
        );

        assertSame(2, count($clients));
        assertTrue($clients[0] instanceof Client);
        assertTrue($clients[1] instanceof Client);

        $mikrotik_description = read_json_object(
            $fixture['clients'] . '/mikrotik.json'
        );
        $bredland_description = read_json_object(
            $fixture['clients'] . '/bredland.json'
        );
        list($mikrotik, $bredland) = $clients;

        assertSame($mikrotik_description['order'], $mikrotik->get_order());
        assertSame($bredland_description['order'], $bredland->get_order());
        assertSame(array('MikroTik', 'Bredland'), client_titles($clients));

        $mikrotik_heartbeat = read_json_object(
            $repoRoot . '/tests/fixtures/heartbeats/mikrotik.json'
        );
        $bredland_heartbeat = read_json_object(
            $repoRoot . '/tests/fixtures/heartbeats/bredland.json'
        );

        $now_epoch = strtotime($fixtureNow);

        assertSame(
            $now_epoch - strtotime($mikrotik_heartbeat['ts']),
            $mikrotik->heartbeat_age()
        );
        assertSame(
            $now_epoch - strtotime($bredland_heartbeat['ts']),
            $bredland->heartbeat_age()
        );

        assertSame('green', $mikrotik->health_colour());
        assertSame('green', $bredland->health_colour());

        assertSame($mikrotik_description['title'], $mikrotik->get_title());
        assertSame($bredland_description['title'], $bredland->get_title());

        assertSame(
            count($mikrotik_description['fields']),
            count($mikrotik->field_list()->fields())
        );
        assertSame(
            count($bredland_description['fields']),
            count($bredland->field_list()->fields())
        );
        assertSame(1, $mikrotik->notification_count());
        assertSame(0, $bredland->notification_count());

        assertSame($mikrotik_heartbeat, $mikrotik->heartbeat());
        assertSame($bredland_heartbeat, $bredland->heartbeat());
    } finally {
        remove_tree($fixture['root']);
    }
});

$runner->test('load() skips a malformed client description', function () use ($repoRoot) {
    $fixture = create_client_loader_fixture($repoRoot);

    try {
        file_put_contents(
            $fixture['clients'] . '/mikrotik.json',
            '{ this is not json'
        );

        $clients = ClientLoader::load(
            $fixture['clients'],
            $fixture['schemas'],
            $fixture['data']
        );

        assertSame(array('Bredland'), client_titles($clients));
    } finally {
        remove_tree($fixture['root']);
    }
});

$runner->test('load() skips a client with a missing schema', function () use ($repoRoot) {
    $fixture = create_client_loader_fixture($repoRoot);

    try {
        unlink($fixture['schemas'] . '/mikrotik.json');

        $clients = ClientLoader::load(
            $fixture['clients'],
            $fixture['schemas'],
            $fixture['data']
        );

        assertSame(array('Bredland'), client_titles($clients));
    } finally {
        remove_tree($fixture['root']);
    }
});

$runner->test('load() skips a client with a malformed schema', function () use ($repoRoot) {
    $fixture = create_client_loader_fixture($repoRoot);

    try {
        file_put_contents(
            $fixture['schemas'] . '/mikrotik.json',
            '{ this is not json'
        );

        $clients = ClientLoader::load(
            $fixture['clients'],
            $fixture['schemas'],
            $fixture['data']
        );

        assertSame(array('Bredland'), client_titles($clients));
    } finally {
        remove_tree($fixture['root']);
    }
});

$runner->test('load() returns a critical unavailable client with a missing heartbeat', function () use ($repoRoot) {
    $fixture = create_client_loader_fixture($repoRoot);

    try {
        unlink(heartbeat_file_for_host($fixture['data'], 'mikrotik'));

        $clients = ClientLoader::load(
                $fixture['clients'],
                $fixture['schemas'],
                $fixture['data']
        );

        assertSame(
                array('MikroTik', 'Bredland'),
                client_titles($clients)
        );

        $client = client_for_host($clients, 'mikrotik');

        assertSame(null, $client->heartbeat());
        assertSame('critical', $client->health());
        assertSame('red', $client->health_colour());
        assertSame('unavailable', $client->formatted_heartbeat_age());

        foreach ($client->field_list()->fields() as $field) {
            assertSame(
                    'unavailable',
                    $client->get($field->field()->value())
            );
        }

        assertSame(0, $client->notification_count());
    } finally {
        remove_tree($fixture['root']);
    }
});

$runner->test('load() returns a critical unavailable client with a malformed heartbeat', function () use ($repoRoot) {
    $fixture = create_client_loader_fixture($repoRoot);

    try {
        file_put_contents(
                heartbeat_file_for_host($fixture['data'], 'mikrotik'),
                "{ this is not json\n"
        );

        $clients = ClientLoader::load(
                $fixture['clients'],
                $fixture['schemas'],
                $fixture['data']
        );

        assertSame(
                array('MikroTik', 'Bredland'),
                client_titles($clients)
        );

        $client = client_for_host($clients, 'mikrotik');

        assertSame(null, $client->heartbeat());
        assertSame('critical', $client->health());
        assertSame('red', $client->health_colour());
        assertSame('unavailable', $client->formatted_heartbeat_age());

        foreach ($client->field_list()->fields() as $field) {
            assertSame(
                    'unavailable',
                    $client->get($field->field()->value())
            );
        }

        assertSame(0, $client->notification_count());
    } finally {
        remove_tree($fixture['root']);
    }
});

if ($previousNow === false) {
    putenv('NOC_NOW');
} else {
    putenv('NOC_NOW=' . $previousNow);
}

$runner->finish();
