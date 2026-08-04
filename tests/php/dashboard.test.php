#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/dashboard.php';
require_once $nocRoot . '/lib/cards-row.php';

$runner = new TestRunner('dashboard');

$tmpdir = sys_get_temp_dir() . '/noc-test-' . uniqid('', true);
if (!mkdir($tmpdir)) throw new RuntimeException('failed to create temporary directory');

$runner->test('returns constructor text', function () use($tmpdir) {
    $template_file = "$tmpdir/noc_template.php";
    $test_contents = <<<'PHP'
<div id="cards-row-test">
</div>
PHP
;
    if (file_put_contents($template_file, "<?php ?>\n$test_contents", FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('failed to create template: ' . $template_file);
    }
    $clients = array(array(), array());
    $cards_row = new CardsRow(0, $clients, $template_file);
    $dashboard = new Dashboard(0, $cards_row);
    assertStringContains($test_contents, $dashboard->render());
    unlink($template_file);
});

rmdir($tmpdir);
$runner->finish();
