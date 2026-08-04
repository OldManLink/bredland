#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/card-container.php';

$runner = new TestRunner('card-container');

$tmpdir = sys_get_temp_dir() . '/card-container-test-' . uniqid('', true);

if (!mkdir($tmpdir)) {
    throw new RuntimeException('failed to create temporary directory');
}

$runner->test('render() renders the card container structure in order', function () use ($tmpdir) {
    $template_file = "$tmpdir/card-view.php";

    try {
        $test_contents = <<<'PHP'
<div class="card">
</div>
PHP
;

        if (file_put_contents($template_file, $test_contents, LOCK_EX) === false) {
            throw new RuntimeException(
                'failed to create template: ' . $template_file
            );
        }

        $client = array();
        $card_container = new CardContainer(1, $client, $template_file);
        $html = $card_container->render();

        assertStringContains('<div class="card-container">', $html);
        assertStringContains('<div class="card">', $html);

        assertSame(1, substr_count($html, '<div class="card-container">'));
        assertSame(1, substr_count($html, '<div class="card">'));

        $card_container_start = strpos($html, '<div class="card-container">');
        $card_start = strpos($html, '<div class="card">');
        $card_end = strpos($html, '</div>', $card_start);
        $card_container_end = strrpos($html, '</div>');

        assertTrue($card_container_start < $card_start);
        assertTrue($card_start < $card_end);
        assertTrue($card_end < $card_container_end);
    } finally {
        if (file_exists($template_file)) {
            unlink($template_file);
        }
    }
});

rmdir($tmpdir);
$runner->finish();
