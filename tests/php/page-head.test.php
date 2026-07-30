#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/page-head.php';
require_once $nocRoot . '/lib/text-renderable.php';

$runner = new TestRunner('page-head');

$runner->test('render() renders the required set of tags', function () use ($nocRoot){
    $page_head = new PageHead(1);
    assertStringStartsWith(indentation(1, "<meta charset=\"utf-8\">\n"), $page_head->render());
    assertStringContains("<meta name=\"viewport\"", $page_head->render());
    assertStringContains("<meta name=\"mobile-web-app-capable\"", $page_head->render());
    assertStringContains("<meta name=\"theme-color\"", $page_head->render());
    assertStringContains("<link rel=\"manifest\"", $page_head->render());
    assertStringContains("<link rel=\"apple-touch-icon\"", $page_head->render());
    assertStringContains("<link rel=\"icon\"", $page_head->render());
    assertStringContains("type=\"image/png\"", $page_head->render());
    assertStringContains("sizes=\"32x32\"", $page_head->render());
    assertStringContains("sizes=\"16x16\"", $page_head->render());
    assertStringContains("<link rel=\"stylesheet\"", $page_head->render());
    assertStringContains("<script src=\"static/dashboard.js", $page_head->render());
    assertStringContains("</script>", $page_head->render());
    assertStringContains(indentation(1, "<title>\n") . indentation(2, "Network Operations Centre\n") . indentation(1, "</title>"), $page_head->render());
    $expected = trim(file_get_contents("$nocRoot/static/static.version"));
    assertSame(2, substr_count($page_head->render(), $expected));
});

$runner->finish();
