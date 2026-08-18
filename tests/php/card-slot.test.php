#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/card-slot.php';

$runner = new TestSuiteRunner('card-slot');

$runner->test('render() renders the card container and telemetry drawer in order', function () {

    $client = test_client(
         array(
             'fields' => array(
                 array(
                     'label' => 'Status',
                     'field' => 'status',
                     'format' => 'display_value'
                 )
             )
         ),
         array(
             'status' => 'ready'
         )
     );

     $card_slot = new CardSlot(1, $client);
     $html = $card_slot->render();

    $card_slot = new CardSlot(1, $client);
    $html = $card_slot->render();

    $card_slot_tag = '<div class="card-slot">';
    $card_container_tag = '<div class="card-container">';
    $card_tag =
        '<div class="card ' .
        $client->health_colour() .
        '">';
    $template_tag = '<template id="test-client-telemetry-template">';
    $telemetry_tag = '<pre class="telemetry">';

    assertSame(1, substr_count($html, $card_slot_tag));
    assertSame(1, substr_count($html, $card_container_tag));
    assertSame(1, substr_count($html, $card_tag));
    assertSame(1, substr_count($html, $template_tag));
    assertSame(1, substr_count($html, $telemetry_tag));
    assertSame(16, substr_count($html, "\n"));


    $card_slot_start = strpos($html, $card_slot_tag);
    $card_container_start = strpos($html, $card_container_tag);
    $card_start = strpos($html, $card_tag);
    $template_start = strpos($html, $template_tag);
    $telemetry_start = strpos($html, $telemetry_tag);
    $card_slot_end = strrpos($html, '</div>');

    assertTrue($card_slot_start < $card_container_start);
    assertTrue($card_container_start < $card_start);
    assertTrue($card_start < $template_start);
    assertTrue($template_start < $telemetry_start);
    assertTrue($telemetry_start < $card_slot_end);
});

$runner->finish();
