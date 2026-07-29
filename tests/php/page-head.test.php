#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/page-head.php';

$runner = new TestRunner('page-head');

$tmpdir = sys_get_temp_dir() . '/page-head-test-' . getmypid();
mkdir($tmpdir);

$runner->test('replaces static version placeholders', function () use($tmpdir, $nocRoot) {
    $placeholder = '_STATIC_VERSION__';
    $clients = array();
    $template_file = "$tmpdir/page-head-template.php";
    $test_contents = <<<'PHP'
<link rel="stylesheet" href="static/style.css?v=__STATIC_VERSION__">
<script src="static/dashboard.js?v=__STATIC_VERSION__"></script>
PHP
;
    if (file_put_contents($template_file, "$test_contents", FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('failed to create template: ' . $template_file);
    }

    $page_head = new PageHead($template_file);
    $html = $page_head->render();
    $expected = trim(file_get_contents("$nocRoot/static/static.version"));

    assertSame(0, substr_count($html, $placeholder));
    assertSame(2, substr_count($html, $expected));
    unlink($template_file);
});

rmdir($tmpdir);
$runner->finish();
