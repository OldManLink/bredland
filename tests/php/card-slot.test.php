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

$runner->test('render() renders the card container and telemetry drawer in order', function () use ($tmpdir) {
    $template_file = "$tmpdir/card-view.php";
    $heartbeat_file = "$tmpdir/test-client.jsonl";

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

        if (file_put_contents($heartbeat_file, "{}\n", LOCK_EX) === false) {
            throw new RuntimeException(
                'failed to create heartbeat file: ' . $heartbeat_file
            );
        }

        $client = array(
            'host' => 'test-client',
            'heartbeat_file' => $heartbeat_file
        );

        $card_slot = new CardSlot(1, $client, $template_file);
        $html = $card_slot->render();

        $card_slot_tag = '<div class="card-slot">';
        $card_container_tag = '<div class="card-container">';
        $card_tag = '<div class="card">';
        $template_tag = '<template id="test-client-telemetry-template">';
        $telemetry_tag = '<pre class="telemetry">';

        assertSame(1, substr_count($html, $card_slot_tag));
        assertSame(1, substr_count($html, $card_container_tag));
        assertSame(1, substr_count($html, $card_tag));
        assertSame(1, substr_count($html, $template_tag));
        assertSame(1, substr_count($html, $telemetry_tag));

        $card_slot_start = strpos($html, $card_slot_tag);
        $card_container_start = strpos($html, $card_container_tag);
        $card_start = strpos($html, $card_tag);
        $template_start = strpos($html, $template_tag);
        $telemetry_start = strpos($html, $telemetry_tag);
        $card_slot_end = strrpos($html, '</div>');

        assertTrue($card_slot_start < $card_container_start);
        assertTrue($card_container_start < $card_start);
        assertTrue($card_start < $template_start);
        assertTrue($template_start < $telemetry_start);
        assertTrue($telemetry_start < $card_slot_end);
    } finally {
        if (file_exists($template_file)) {
            unlink($template_file);
        }

        if (file_exists($heartbeat_file)) {
            unlink($heartbeat_file);
        }
    }
});

rmdir($tmpdir);
$runner->finish();
