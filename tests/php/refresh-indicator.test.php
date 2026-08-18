#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/refresh-indicator.php';

$runner = new TestSuiteRunner('refresh-indicator');

$runner->test('render() renders the required set of tags', function () {
    $refresh_indicator = new RefreshIndicator(1);
    $html = $refresh_indicator->render();

    assertStringStartsWith(indentation(1, "<div id=\"refresh-indicator\">"), $html);
    assertStringContains("<div class=\"spinner\"></div>", $html);
});

$runner->finish();
