#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/dashboard.php';
require_once $nocRoot . '/lib/cards-row.php';

$runner = new TestSuiteRunner('dashboard');

$runner->test('render() renders the cards row inside the dashboard', function () {
    $cards_row = new CardsRow(1, array());
    $dashboard = new Dashboard(0, $cards_row);
    $html = $dashboard->render();

    assertSame(1, substr_count($html, '<div class="dashboard">'));
    assertSame(1, substr_count($html, '<div class="cards-row">'));

    $dashboard_start = strpos($html, '<div class="dashboard">');
    $cards_row_start = strpos($html, '<div class="cards-row">');
    $dashboard_end = strrpos($html, '</div>');

    assertTrue($dashboard_start < $cards_row_start);
    assertTrue($cards_row_start < $dashboard_end);

    assertSame(4, substr_count($html, "\n"));

});

$runner->finish();
