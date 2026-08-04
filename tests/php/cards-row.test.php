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

$runner->test('render() renders no card slots for no clients', function () use ($tmpdir) {
    $template_file = "$tmpdir/card-slot-view.php";

    try {
        if (file_put_contents($template_file, '', LOCK_EX) === false) {
            throw new RuntimeException(
                'failed to create template: ' . $template_file
            );
        }

        $cards_row = new CardsRow(1, array(), $template_file);
        $html = $cards_row->render();

        assertStringStartsWith(
            indentation(1, '<div class="cards-row">'),
            $html
        );
        assertSame(0, substr_count($html, '<div class="card-slot">'));
    } finally {
        if (file_exists($template_file)) {
            unlink($template_file);
        }
    }
});

$runner->test('render() renders one card slot for one client', function () use ($tmpdir) {
    $template_file = "$tmpdir/card-slot-view.php";

    try {
        $test_contents = <<<'PHP'
        <div class="client-marker"><?= $client['marker'] ?></div>
PHP
;

        if (file_put_contents($template_file, $test_contents, LOCK_EX) === false) {
            throw new RuntimeException(
                'failed to create template: ' . $template_file
            );
        }

        $clients = array(
            array('marker' => 'first-client')
        );

        $cards_row = new CardsRow(1, $clients, $template_file);
        $html = $cards_row->render();

        assertSame(1, substr_count($html, '<div class="card-slot">'));
        assertStringContains('first-client', $html);
    } finally {
        if (file_exists($template_file)) {
            unlink($template_file);
        }
    }
});

$runner->test('render() preserves client order', function () use ($tmpdir) {
    $template_file = "$tmpdir/card-slot-view.php";

    try {
        $test_contents = <<<'PHP'
        <div class="client-marker"><?= $client['marker'] ?></div>
PHP
;

        if (file_put_contents($template_file, $test_contents, LOCK_EX) === false) {
            throw new RuntimeException(
                'failed to create template: ' . $template_file
            );
        }

        $clients = array(
            array('marker' => 'first-client'),
            array('marker' => 'second-client')
        );

        $cards_row = new CardsRow(1, $clients, $template_file);
        $html = $cards_row->render();

        assertSame(2, substr_count($html, '<div class="card-slot">'));
        assertSame(1, substr_count($html, 'first-client'));
        assertSame(1, substr_count($html, 'second-client'));

        $cards_row_start = strpos($html, '<div class="cards-row">');
        $first_client = strpos($html, 'first-client');
        $second_client = strpos($html, 'second-client');
        $cards_row_end = strrpos($html, '</div>');

        assertTrue($cards_row_start < $first_client);
        assertTrue($first_client < $second_client);
        assertTrue($second_client < $cards_row_end);
    } finally {
        if (file_exists($template_file)) {
            unlink($template_file);
        }
    }
});

rmdir($tmpdir);
$runner->finish();
