#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/noc.php';
require_once $nocRoot . '/lib/cards-row.php';
require_once $nocRoot . '/lib/dashboard.php';
require_once $nocRoot . '/lib/text-renderable.php';

$runner = new TestRunner('noc');

$tmpdir = sys_get_temp_dir() . '/noc-test-' . uniqid('', true);

if (!mkdir($tmpdir)) {
    throw new RuntimeException('failed to create temporary directory');
}

$runner->test('render() renders the complete noc', function () use ($tmpdir){
try {
    $clients = array(array(), array());
    $template_file = "$tmpdir/card-container-template.php";
    $test_contents = <<<'PHP'
<div class="card-container-view">
</div>
PHP
;
    if (file_put_contents($template_file, "$test_contents", LOCK_EX) === false) {
        throw new RuntimeException('failed to create template: ' . $template_file);
    }
    $cards_row = new CardsRow(2, $clients, $template_file);
    $noc = new Noc(new Dashboard(1, $cards_row));
    $html = $noc->render();

    assertStringContains('<!DOCTYPE html>', $html);
    assertStringContains('<html lang="en">', $html);
    assertStringContains('<head>', $html);
    assertStringContains('</head>', $html);
    assertStringContains('<body>', $html);
    assertStringContains('<div id="refresh-indicator">', $html);
    assertStringContains('<div class="dashboard">', $html);
    assertStringContains('<div class="cards-row">', $html);
    assertStringContains('<div class="card-slot">', $html);
    assertStringContains('<div class="card-container-view">', $html);
    assertStringContains('</body>', $html);
    assertStringContains('</html>', $html);

    assertSame(1, substr_count($html, '<!DOCTYPE html>'));
    assertSame(1, substr_count($html, '<html lang="en">'));
    assertSame(1, substr_count($html, '<div id="refresh-indicator">'));
    assertSame(1, substr_count($html, '<div class="dashboard">'));
    assertSame(1, substr_count($html, '<div class="cards-row">'));
    assertSame(2, substr_count($html, '<div class="card-slot">'));
    assertSame(2, substr_count($html, '<div class="card-container-view">'));

    $head_start = strpos($html, '<head>');
    $head_end = strpos($html, '</head>');
    $body_start = strpos($html, '<body>');
    $refresh_indicator = strpos($html, '<div id="refresh-indicator">');
    $dashboard = strpos($html, '<div class="dashboard">');
    $cards_row = strpos($html, '<div class="cards-row">');
    $card_slot = strpos($html, '<div class="card-slot">');
    $card_slot = strpos($html, '<div class="card-container-view">');
    $body_end = strpos($html, '</body>');
    $html_end = strpos($html, '</html>');

    assertTrue($head_start < $head_end);
    assertTrue($head_end < $body_start);
    assertTrue($body_start < $refresh_indicator);
    assertTrue($refresh_indicator < $dashboard);
    assertTrue($dashboard < $cards_row);
    assertTrue($cards_row < $card_slot);
    assertTrue($card_slot < $body_end);
    assertTrue($body_end < $html_end);}
finally {
    if (file_exists($template_file)) {
        unlink($template_file);
    }
}
});

rmdir($tmpdir);
$runner->finish();
