#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once __DIR__ . '/lib/test-runner.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/html-renderable.php';
require_once $nocRoot . '/lib/text-renderable.php';

class DummyRenderable extends HtmlRenderable {
    protected function render_html($compact) {
        return "Hello\n";
    }
}

$runner = new TestRunner('html-renderable');

$runner->test('render() delegates to render_html()', function ()  {
    $renderer = new DummyRenderable(0);
    assertSame("Hello\n",$renderer->render());
});

$runner->test('indent() returns the correct indentation', function () {
    assertSame(indentation(0), (new DummyRenderable(0))->indent());
    assertSame(indentation(1), (new DummyRenderable(1))->indent());
    assertSame(indentation(2), (new DummyRenderable(2))->indent());
});

$runner->test('child_indentation_level() increments indentation', function () {
    assertSame(1, (new DummyRenderable(0))->child_indentation_level());
    assertSame(2, (new DummyRenderable(1))->child_indentation_level());
    assertSame(3, (new DummyRenderable(2))->child_indentation_level());
});

$runner->test('tag() renders an empty tag at the current indentation', function () {
    assertSame("<head>\n</head>\n", (new DummyRenderable(0))->tag('head', array(), array()));
    assertSame(indentation(1, "<body>\n") . indentation(1, "</body>\n"), (new DummyRenderable(1))->tag('body', array(), array()));
});

$runner->test('tag() renders a single child', function () {

    assertSame(
        "<head>\n" . indentation(1, "Hello\n</head>\n"),
        (new DummyRenderable(0))->tag('head',array(), array(new TextRenderable(1, "Hello")))
    );
});

$runner->test('tag() renders HTML void elements without an end tag', function () {

    $renderer = new DummyRenderable(0);

    assertSame("<meta charset=\"utf-8\">\n", $renderer->tag('meta', array('charset' => 'utf-8'), array()));
    assertSame(
        "<link rel=\"stylesheet\" href=\"style.css\">\n",
        $renderer->tag('link', array('rel'  => 'stylesheet', 'href' => 'style.css'), array())
    );

});

$runner->finish();

