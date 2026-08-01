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
    $clients = array();
    $template_file = "$tmpdir/card-slot-template.php";
    $test_contents = <<<'PHP'
<div id="card-slot-test">
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
    assertStringContains('<div class="dashboard">', $html);
    assertStringContains('<div class="cards-row">', $html);
    assertStringContains('<div id="card-slot-test">', $html);
    assertStringContains('</body>', $html);
    assertStringContains('</html>', $html);

    assertSame(1, substr_count($html, '<!DOCTYPE html>'));
    assertSame(1, substr_count($html, '<html lang="en">'));
    assertSame(1, substr_count($html, '<div class="dashboard">'));
    assertSame(1, substr_count($html, '<div class="cards-row">'));
    assertSame(1, substr_count($html, '<div id="card-slot-test">'));

    assertTrue(strpos($html, '<head>') < strpos($html, '</head>'));
    assertTrue(strpos($html, '</head>') < strpos($html, '<body>'));
    assertTrue(strpos($html, '<body>') < strpos($html, '<div class="dashboard">'));
    assertTrue(strpos($html, 'div class="dashboard">') < strpos($html, '<div class="cards-row">'));
    assertTrue(strpos($html, '<div class="cards-row">') < strpos($html, '<div id="card-slot-test">'));
    assertTrue(strpos($html, '<div id="card-slot-test">') < strpos($html, '</body>'));
    assertTrue(strpos($html, '</body>') < strpos($html, '</html>'));
}
finally {
    if (file_exists($template_file)) {
        unlink($template_file);
    }
}
});

rmdir($tmpdir);
$runner->finish();
