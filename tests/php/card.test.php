#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/card.php';

$runner = new TestRunner('card');

$runner->test('render() renders the complete card in order', function () {
    $client = array(
        'age' => 65,
        'title' => 'Test client',
        'host' => 'test-client',
        'heartbeat' => array(
            'status' => 'ready'
        ),
        'fields' => array(
            array(
                'label' => 'Status',
                'field' => 'status',
                'value_type' => 'string'
            )
        )
    );

    $card = new Card(1, $client);
    $html = $card->render();

    $health_colour = heartbeat_health_colour($client['age']);
    $card_tag = '<div class="card ' . $health_colour . '">';
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

$runner->finish();
