#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/page-head.php';
require_once $nocRoot . '/lib/text-renderable.php';

$runner = new TestRunner('page-head');
$static_version = trim(file_get_contents("$nocRoot/static/static.version"));

$runner->test('render() renders the required set of tags', function () use ($static_version){
    $page_head = new PageHead(1);
    $html = $page_head->render();

    assertStringStartsWith(indentation(1, "<meta charset=\"utf-8\">\n"), $html);
    assertStringContains("<meta name=\"viewport\"", $html);
    assertStringContains("<meta name=\"mobile-web-app-capable\"", $html);
    assertStringContains("<meta name=\"theme-color\"", $html);
    assertStringContains("<link rel=\"manifest\"", $html);
    assertStringContains("<link rel=\"apple-touch-icon\"", $html);
    assertStringContains("<link rel=\"icon\"", $html);
    assertStringContains("type=\"image/png\"", $html);
    assertStringContains("sizes=\"32x32\"", $html);
    assertStringContains("sizes=\"16x16\"", $html);
    assertStringContains("<link rel=\"stylesheet\" href=\"static/style.css?v=$static_version\">", $html);
    assertStringContains("<script src=\"static/dashboard.js?v=$static_version\"></script>", $html);
    assertStringContains("<title>Network Operations Centre</title>", $html);
});

$runner->finish();
