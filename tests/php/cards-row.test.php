#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/cards-row.php';

$runner = new TestRunner('cards-row');

$tmpdir = sys_get_temp_dir() . '/cards-row-test-' . uniqid('', true);
if (!mkdir($tmpdir)) throw new RuntimeException('failed to create temporary directory');

$runner->test('render() renders the required set of tags', function () use ($tmpdir){
try {
    $clients = array();
    $template_file = "$tmpdir/noc_template.php";
    $test_contents = <<<'PHP'
        <div class="card-slot">
        </div>
PHP
;
    if (file_put_contents($template_file, "$test_contents", LOCK_EX) === false) {
        throw new RuntimeException('failed to create template: ' . $template_file);
    }
    $clients = array();
    $cards_row = new CardsRow(1, $clients, $template_file);
    $html = $cards_row->render();

    assertStringStartsWith(indentation(1, "<div class=\"cards-row\">"), $html);
    assertStringContains(indentation(2, "<div class=\"card-slot\">"), $html);
    assertSame(2, substr_count($html, "</div>"));
}
finally {
    if (file_exists($template_file)) {
        unlink($template_file);
    }
}
});

rmdir($tmpdir);
$runner->finish();
