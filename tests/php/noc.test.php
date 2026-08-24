#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/noc.php';
require_once $nocRoot . '/lib/cards-row.php';
require_once $nocRoot . '/lib/dashboard.php';

$runner = new TestSuiteRunner('noc');

$runner->test('render() renders the complete noc', function () {
    $cards_row = new CardsRow(2, array());
    $noc = new Noc(new Dashboard(1, $cards_row));
    $html = $noc->render();

    assertStringContains('<!DOCTYPE html>', $html);
    assertStringContains('<html lang="en">', $html);
    assertStringContains('<head>', $html);
    assertStringContains('</head>', $html);
    assertStringContains('<body>', $html);
    assertStringContains('<div id="refresh-indicator">', $html);
    assertStringContains('<div class="dashboard">', $html);
    assertStringContains('<div class="cards-row">', $html);
    assertStringContains('</body>', $html);
    assertStringContains('</html>', $html);

    assertSame(1, substr_count($html, '<!DOCTYPE html>'));
    assertSame(1, substr_count($html, '<html lang="en">'));
    assertSame(1, substr_count($html, '<div id="refresh-indicator">'));
    assertSame(1, substr_count($html, '<div class="dashboard">'));
    assertSame(1, substr_count($html, '<div class="cards-row">'));
    assertSame(0, substr_count($html, '<div class="card-slot">'));
    assertSame(0, substr_count($html, '<div class="card-container">'));

    $head_start = strpos($html, '<head>');
    $head_end = strpos($html, '</head>');
    $body_start = strpos($html, '<body>');
    $refresh_indicator = strpos($html, '<div id="refresh-indicator">');
    $dashboard = strpos($html, '<div class="dashboard">');
    $cards_row = strpos($html, '<div class="cards-row">');
    $body_end = strpos($html, '</body>');
    $html_end = strpos($html, '</html>');

    assertTrue($head_start < $head_end);
    assertTrue($head_end < $body_start);
    assertTrue($body_start < $refresh_indicator);
    assertTrue($refresh_indicator < $dashboard);
    assertTrue($dashboard < $cards_row);
    assertTrue($cards_row < $body_end);
    assertTrue($body_end < $html_end);

    assertSame(26, substr_count($html, "\n"));
});

$runner->finish();
