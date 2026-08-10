#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/card-head.php';

$runner = new TestRunner('card-head');

$runner->test('render() renders the health indicator and escaped title', function () {
    $client = test_client(
        array(
            'title' => 'Test <&" client'
        )
    );

    $card_head = new CardHead(1, $client);
    $html = $card_head->render();

    $health_colour = $client->health_colour();
    $escaped_title = htmlspecialchars(
        $client->get_title(),
        ENT_QUOTES,
        'UTF-8'
    );

    $heading_tag = '<h1>';
    $indicator_tag =
        '<span class="led ' . $health_colour . '"></span>';

    assertStringStartsWith(
        indentation(1, $heading_tag),
        $html
    );

    assertStringContains($indicator_tag, $html);
    assertStringContains($escaped_title, $html);
    assertStringContains('</h1>', $html);

    assertSame(1, substr_count($html, '<h1>'));
    assertSame(1, substr_count($html, '</h1>'));
    assertSame(1, substr_count($html, $indicator_tag));
    assertSame(1, substr_count($html, $escaped_title));
    assertSame(0, substr_count($html, $client->get_title()));
    assertSame(4, substr_count($html, "\n"));

    $heading_start = strpos($html, $heading_tag);
    $indicator_start = strpos($html, $indicator_tag);
    $title_start = strpos($html, $escaped_title);
    $heading_end = strpos($html, '</h1>');

    assertTrue($heading_start < $indicator_start);
    assertTrue($indicator_start < $title_start);
    assertTrue($title_start < $heading_end);
});

$runner->finish();
