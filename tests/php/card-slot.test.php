#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/card-slot.php';

$runner = new TestRunner('card-slot');

$tmpdir = sys_get_temp_dir() . '/card-slot-test-' . uniqid('', true);

if (!mkdir($tmpdir)) {
    throw new RuntimeException('failed to create temporary directory');
}

$runner->test('render() renders the card slot structure in order', function () use ($tmpdir) {
    $template_file = "$tmpdir/card-slot-view.php";

    try {
        $test_contents = <<<'PHP'
        <div class="card-container">
        </div>
        <template>
        </template>
PHP
;

        if (file_put_contents($template_file, "$test_contents", LOCK_EX) === false) {
            throw new RuntimeException(
                'failed to create template: ' . $template_file
            );
        }

        $client = array();
        $card_slot = new CardSlot(1, $client, $template_file);
        $html = $card_slot->render();

        assertStringContains('<div class="card-slot">', $html);
        assertStringContains('<div class="card-container">', $html);
        assertStringContains('<template>', $html);
        assertStringContains('</template>', $html);

        assertSame(1, substr_count($html, '<div class="card-slot">'));
        assertSame(1, substr_count($html, '<div class="card-container">'));
        assertSame(1, substr_count($html, '<template>'));
        assertSame(1, substr_count($html, '</template>'));

        $card_slot_start = strpos($html, '<div class="card-slot">');
        $card_container_start = strpos($html, '<div class="card-container">');
        $card_container_end = strpos($html, '</div>', $card_container_start);
        $template_start = strpos($html, '<template>');
        $template_end = strpos($html, '</template>');
        $card_slot_end = strrpos($html, '</div>');

        assertTrue($card_slot_start < $card_container_start);
        assertTrue($card_container_start < $card_container_end);
        assertTrue($card_container_end < $template_start);
        assertTrue($template_start < $template_end);
        assertTrue($template_end < $card_slot_end);
    } finally {
        if (file_exists($template_file)) {
            unlink($template_file);
        }
    }
});

rmdir($tmpdir);
$runner->finish();
