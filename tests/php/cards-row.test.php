#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/cards-row.php';

$runner = new TestRunner('cards-row');

$tmpdir = sys_get_temp_dir() . '/cards-row-test-' . uniqid('', true);

if (!mkdir($tmpdir)) {
    throw new RuntimeException('failed to create temporary directory');
}

function cards_row_test_client($tmpdir, $host) {
    $heartbeat_file = "$tmpdir/$host.jsonl";

    if (file_put_contents($heartbeat_file, "{\"status\":\"ready\"}\n", LOCK_EX) === false) {
        throw new RuntimeException(
            'failed to create heartbeat file: ' . $heartbeat_file
        );
    }

    return array(
        'host' => $host,
        'title' => $host,
        'age' => 65,
        'heartbeat_file' => $heartbeat_file,
        'heartbeat' => array(
            'status' => 'ready'
        ),
        'fields' => array(
            array(
                'label' => 'Status',
                'field' => 'status',
                'value_type' => 'string'
            )
        )
    );
}

$runner->test('render() renders no card slots for no clients', function () {
    $cards_row = new CardsRow(1, array());
    $html = $cards_row->render();

    assertStringStartsWith(
        indentation(1, '<div class="cards-row">'),
        $html
    );
    assertSame(0, substr_count($html, '<div class="card-slot">'));
    assertSame(2, substr_count($html, "\n"));

});

$runner->test('render() renders one card slot for one client', function () use ($tmpdir) {
    $client = cards_row_test_client($tmpdir, 'first-client');

    try {
        $cards_row = new CardsRow(1, array($client));
        $html = $cards_row->render();

        assertSame(1, substr_count($html, '<div class="card-slot">'));
        assertStringContains('first-client', $html);
    } finally {
        unlink($client['heartbeat_file']);
    }
});

$runner->test('render() preserves client order', function () use ($tmpdir) {
    $first_client = cards_row_test_client($tmpdir, 'first-client');
    $second_client = cards_row_test_client($tmpdir, 'second-client');

    try {
        $cards_row = new CardsRow(
            1,
            array(
                $first_client,
                $second_client
            )
        );
        $html = $cards_row->render();

        assertSame(2, substr_count($html, '<div class="card-slot">'));
        assertTrue(substr_count($html, 'first-client') >= 1);
        assertTrue(substr_count($html, 'second-client') >= 1);

        $cards_row_start = strpos($html, '<div class="cards-row">');
        $first_client_start = strpos($html, 'first-client');
        $second_client_start = strpos($html, 'second-client');
        $cards_row_end = strrpos($html, '</div>');

        assertTrue($cards_row_start < $first_client_start);
        assertTrue($first_client_start < $second_client_start);
        assertTrue($second_client_start < $cards_row_end);
    } finally {
        unlink($first_client['heartbeat_file']);
        unlink($second_client['heartbeat_file']);
    }
});

rmdir($tmpdir);
$runner->finish();
