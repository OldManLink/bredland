#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/drawer-handle.php';

$runner = new TestSuiteRunner('drawer-handle');

$runner->test('render() renders an escaped telemetry drawer handle', function () {
    $client = test_client(
        array('host' => 'test<&"client')
    );

    $drawer_handle = new DrawerHandle(1, $client);
    $html = $drawer_handle->render();

    $escaped_host = htmlspecialchars(
        $client->host()->value(),
        ENT_QUOTES,
        'UTF-8'
    );

    $opening_tag =
        '<button class="drawer-handle" type="button" ' .
        'data-telemetry-toggle="' . $escaped_host . '">';

    assertStringStartsWith(
        indentation(1, $opening_tag),
        $html
    );

    assertStringContains('>=</button>', $html);

    assertSame(1, substr_count($html, '<button '));
    assertSame(1, substr_count($html, '</button>'));
    assertSame(1, substr_count($html, 'class="drawer-handle"'));
    assertSame(1, substr_count($html, 'type="button"'));
    assertSame(
        1,
        substr_count(
            $html,
            'data-telemetry-toggle="' . $escaped_host . '"'
        )
    );
    assertSame(0, substr_count($html, $client->host()->value()));
    assertSame(1, substr_count($html, "\n"));

    $button_start = strpos($html, '<button ');
    $class_start = strpos($html, 'class="drawer-handle"');
    $type_start = strpos($html, 'type="button"');
    $toggle_start = strpos($html, 'data-telemetry-toggle="');
    $label_start = strpos($html, '>=');
    $button_end = strpos($html, '</button>');

    assertTrue($button_start < $class_start);
    assertTrue($class_start < $type_start);
    assertTrue($type_start < $toggle_start);
    assertTrue($toggle_start < $label_start);
    assertTrue($label_start < $button_end);
});

$runner->finish();
