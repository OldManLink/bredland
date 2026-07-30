#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once __DIR__ . '/lib/test-runner.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/text-renderable.php';

$runner = new TestRunner('text-renderable');

$runner->test('render() returns indented text', function () {
    assertSame("Hello\n", (new TextRenderable(0, "Hello"))->render());
    assertSame(indentation(1, "Hello\n"), (new TextRenderable(1, "Hello"))->render());
});

$runner->finish();

