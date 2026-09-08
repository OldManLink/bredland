#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/card.php';

$runner = new TestSuiteRunner('card');

$runner->test('render() renders the complete card in order', function () {
    with_noc_now('2026-08-10T12:01:05Z', function () {
        $client = test_client(
            array(
                'title' => 'Test client',
                'fields' => array(
                    array(
                        'label' => 'Status',
                        'field' => 'status',
                        'format' => 'display_value'
                    )
                )
            ),
            array(
                'ts' => '2026-08-10T12:00:00Z',
                 'status' => 'ready'
            )
        );

        $card = new Card(1, $client);
        $html = $card->render();

        $card_tag = '<div class="card ' . $client->health_colour() . '">';
        $head_tag = '<h1>';
        $age_text = 'Last heartbeat: 1m 5s ago';
        $field_text = 'Status: ready';
        $drawer_tag =
            '<button class="drawer-handle" type="button" ' .
            'data-telemetry-toggle="test-client">';

        assertStringStartsWith(
            indentation(1, $card_tag),
            $html
        );

        assertSame(1, substr_count($html, $card_tag));
        assertSame(1, substr_count($html, $head_tag));
        assertSame(1, substr_count($html, $age_text));
        assertSame(1, substr_count($html, $field_text));
        assertSame(1, substr_count($html, $drawer_tag));
        assertSame(9, substr_count($html, "\n"));

        $card_start = strpos($html, $card_tag);
        $head_start = strpos($html, $head_tag);
        $age_start = strpos($html, $age_text);
        $field_start = strpos($html, $field_text);
        $drawer_start = strpos($html, $drawer_tag);
        $card_end = strrpos($html, '</div>');

        assertTrue($card_start < $head_start);
        assertTrue($head_start < $age_start);
        assertTrue($age_start < $field_start);
        assertTrue($field_start < $drawer_start);
        assertTrue($drawer_start < $card_end);
    });
});

$runner->test('render() does not render a notification panel when there are no notifications', function () {
    $client = test_client();

    $card = new Card(1, $client);
    $html = $card->render();

    assertSame(0, substr_count($html, 'notification-panel'));
});

$runner->test('render() renders a notification panel when there are notifications', function () {
    $client = test_client();
    $client->addNotification(new Notification('RouterOS update available'));

    $card = new Card(1, $client);
    $html = $card->render();

    assertSame(1, substr_count($html, 'class="notification-panel '));
    assertSame(1, substr_count($html, 'RouterOS update available'));
});

$runner->finish();
