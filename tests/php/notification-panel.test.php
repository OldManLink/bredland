#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/notification-panel.php';
require_once $nocRoot . '/lib/notification.php';

$runner = new TestSuiteRunner('notification-panel');

$runner->test('render() renders a notification panel div', function () {
    $client = test_client();
    $client->addNotification('RouterOS update available');
    $panel = new NotificationPanel(1, $client);

    $html = $panel->render();

    assertStringStartsWith(indentation(1, '<div id="test-client-notification-panel"'), $html);
    assertSame(1, substr_count($html, 'class="notification-panel '));
    assertSame(2, substr_count($html, '<div'));
    assertSame(2, substr_count($html, '</div>'));
    assertStringContains('RouterOS update available', $html);
    assertSame(6, substr_count($html, "\n"));
});

$runner->test('render() renders all notifications', function () {
    $client = test_client();
    $client->addNotification('First notification');
    $client->addNotification('Second notification');
    $panel = new NotificationPanel(1, $client);

    $html = $panel->render();

    assertSame(1, substr_count($html, 'First notification'));
    assertSame(1, substr_count($html, 'Second notification'));
    assertSame(2, substr_count($html, 'class="notification-text"'));
});

$runner->test('render() renders the panel hidden', function () {
    $client = test_client();

    $client->addNotification('Something happened');

    $panel = new NotificationPanel(1, $client);
    $html = $panel->render();

    assertSame(1, substr_count($html, 'class="notification-panel hidden"'));
});

$runner->test('render() renders a close button', function () {
    $client = test_client();

    $client->addNotification('Something happened');

    $panel = new NotificationPanel(1, $client);
    $html = $panel->render();

    assertSame(1, substr_count($html, 'class="notification-panel-close"' ));
    assertSame(1, substr_count($html, '>×</button>'));
});

$runner->test('render() wraps each notification in notification-text', function () {
    $client = test_client();

    $client->addNotification(
        "Software update available:\nVersion 7.23.3"
    );

    $panel = new NotificationPanel(1, $client);
    $html = $panel->render();

    assertSame(
        1,
        substr_count(
            $html,
            'class="notification-text"'
        )
    );

    assertStringContains(
        "Software update available:\nVersion 7.23.3",
        $html
    );
});

$runner->finish();
