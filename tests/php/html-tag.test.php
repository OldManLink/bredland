#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/html-tag.php';

$runner = new TestSuiteRunner('html-tag');

$runner->test('renders an empty tag with separate closing tag', function () {
    $html_tag = new HtmlTag(0, 'pre', array(), array('id' => 'foo'));
    assertSame("<pre id=\"foo\">\n</pre>\n", $html_tag->render());
});

$runner->test('renders a compact empty tag', function () {
    $html_tag = new HtmlTag(0, 'pre', array(), array('id' => 'foo'), true);
    assertSame("<pre id=\"foo\"></pre>\n", $html_tag->render());
});

$runner->test('indents a nested tag', function () {
    $child_tag = new HtmlTag(1, 'pre', array(), array('id' => 'foo'));
    $html_tag = new HtmlTag(0, 'div', array($child_tag), array('class' => 'bar'));
    assertSame("<div class=\"bar\">\n    <pre id=\"foo\">\n    </pre>\n</div>\n", $html_tag->render());
});

$runner->test('preserves compact rendering for a nested tag', function () {
    $child_tag = new HtmlTag(1, 'pre', array(), array('id' => 'foo'), true);
    $html_tag = new HtmlTag(0, 'div', array($child_tag), array('class' => 'bar'));
    assertSame("<div class=\"bar\">\n    <pre id=\"foo\"></pre>\n</div>\n", $html_tag->render());
});

$runner->finish();
