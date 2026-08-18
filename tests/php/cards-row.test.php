#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/cards-row.php';

$runner = new TestSuiteRunner('cards-row');

$runner->test('render() renders no card slots for no clients', function () {
    $cards_row = new CardsRow(1, array());
    $html = $cards_row->render();

    assertStringStartsWith(
        indentation(1, '<div class="cards-row">'),
        $html
    );
    assertSame(0, substr_count($html, '<div class="card-slot">'));
    assertSame(2, substr_count($html, "\n"));
});

$runner->test('render() renders one card slot for one client', function () {
    $client = test_client(
        array(
            'title' => 'First client'
        )
    );

    $cards_row = new CardsRow(1, array($client));
    $html = $cards_row->render();

    assertSame(1, substr_count($html, '<div class="card-slot">'));
    assertStringContains('First client', $html);
});

$runner->test('render() preserves client order', function () {
    $first_client = test_client(
        array(
            'host' => 'first-client',
            'title' => 'First client'
        )
    );

    $second_client = test_client(
        array(
            'host' => 'second-client',
            'title' => 'Second client'
        )
    );

    $cards_row = new CardsRow(
        1,
        array(
            $first_client,
            $second_client
        )
    );
    $html = $cards_row->render();

    assertSame(2, substr_count($html, '<div class="card-slot">'));
    assertSame(1, substr_count($html, 'First client'));
    assertSame(1, substr_count($html, 'Second client'));

    $cards_row_start = strpos($html, '<div class="cards-row">');
    $first_client_start = strpos($html, 'First client');
    $second_client_start = strpos($html, 'Second client');
    $cards_row_end = strrpos($html, '</div>');

    assertTrue($cards_row_start < $first_client_start);
    assertTrue($first_client_start < $second_client_start);
    assertTrue($second_client_start < $cards_row_end);
});

$runner->finish();
