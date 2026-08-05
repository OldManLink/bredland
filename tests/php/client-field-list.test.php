#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/client-field-list.php';

$runner = new TestRunner('client-field-list');

$runner->test('render() renders no client fields for an empty list', function () {
    $client = array(
        'heartbeat' => array()
    );

    $client_field_list = new ClientFieldList(
        1,
        $client,
        array()
    );

    $html = $client_field_list->render();

    assertSame('', $html);
    assertSame(0, substr_count($html, '<p>'));
});

$runner->test('render() renders one client field', function () {
    $client = array(
        'heartbeat' => array(
            'first' => 'first-value'
        )
    );

    $fields = array(
        array(
            'label' => 'First field',
            'field' => 'first',
            'value_type' => 'string'
        )
    );

    $client_field_list = new ClientFieldList(
        1,
        $client,
        $fields
    );

    $html = $client_field_list->render();

    assertSame(1, substr_count($html, '<p>'));
    assertSame(1, substr_count($html, '</p>'));
    assertStringContains('First field: first-value', $html);
});

$runner->test('render() preserves client field order', function () {
    $client = array(
        'heartbeat' => array(
            'first' => 'first-value',
            'second' => 'second-value'
        )
    );

    $fields = array(
        array(
            'label' => 'First field',
            'field' => 'first',
            'value_type' => 'string'
        ),
        array(
            'label' => 'Second field',
            'field' => 'second',
            'value_type' => 'string'
        )
    );

    $client_field_list = new ClientFieldList(
        1,
        $client,
        $fields
    );

    $html = $client_field_list->render();

    assertSame(2, substr_count($html, '<p>'));
    assertSame(2, substr_count($html, '</p>'));
    assertSame(1, substr_count($html, 'First field: first-value'));
    assertSame(1, substr_count($html, 'Second field: second-value'));

    $first_field = strpos($html, 'First field: first-value');
    $second_field = strpos($html, 'Second field: second-value');

    assertTrue($first_field < $second_field);
});

$runner->finish();
