#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/notification-badge.php';

$runner = new TestRunner('notification-badge');

$runner->test('render() renders the notification count', function () {
    $badge = new NotificationBadge(1, 2);
    $html = $badge->render();

    $tag = '<span class="notification-badge">2</span>';

    assertStringStartsWith(
        indentation(1, $tag),
        $html
    );

    assertSame(1, substr_count($html, '<span'));
    assertSame(1, substr_count($html, '</span>'));
    assertSame(1, substr_count($html, 'notification-badge'));
    assertSame(1, substr_count($html, '>2</span>'));
    assertSame(1, substr_count($html, "\n"));
});

$runner->test('render() renders multi-digit counts', function () {
    $badge = new NotificationBadge(1, 12);
    $html = $badge->render();

    assertStringContains(
        '<span class="notification-badge">12</span>',
        $html
    );
});

$runner->finish();
