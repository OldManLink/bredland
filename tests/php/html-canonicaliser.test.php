#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once __DIR__ . '/lib/test-runner.php';

$runner = new TestRunner('html-canonicaliser');

$runner->test('normalises whitespace between tags', function () {
    $html = "<title>NOC</title>
    </head>";

    assertSame(
        "<title>NOC</title>\n</head>",
        canonicalise_html($html)
    );
});

$runner->test('puts adjacent tags on separate lines', function () {
    assertSame(
        "<title>NOC</title>\n</head>",
        canonicalise_html('<title>NOC</title></head>')
    );
});

$runner->finish();
