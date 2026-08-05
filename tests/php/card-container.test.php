#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/card-container.php';

$runner = new TestRunner('card-container');

$runner->test('render() renders the card container structure in order', function () {
    $client = array();
    $card_container = new CardContainer(1, $client);
    $html = $card_container->render();

    assertStringContains('<div class="card-container">', $html);
    assertStringContains('<div class="card red">', $html);

    assertSame(1, substr_count($html, '<div class="card-container">'));
    assertSame(1, substr_count($html, '<div class="card red">'));
    assertSame(10, substr_count($html, "\n"));

    $card_container_start = strpos($html, '<div class="card-container">');
    $card_start = strpos($html, '<div class="card red">');
    $card_end = strpos($html, '</div>', $card_start);
    $card_container_end = strrpos($html, '</div>');

    assertTrue($card_container_start < $card_start);
    assertTrue($card_start < $card_end);
    assertTrue($card_end < $card_container_end);
});

$runner->finish();
