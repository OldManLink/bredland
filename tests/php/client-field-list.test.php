#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/client-field-list.php';

$runner = new TestSuiteRunner('client-field-list');

$runner->test('render() renders no client fields for an empty list', function () {
    $client = test_client();

    $client_field_list = new ClientFieldList(
        1,
        $client
    );

    $html = $client_field_list->render();

    assertSame('', $html);
    assertSame(0, substr_count($html, '<p>'));
});

$runner->test('render() renders one client field', function () {
    $client = test_client(
        array(
            'fields' => array(
                array(
                    'label' => 'First field',
                    'field' => 'status',
                    'format' => 'display_value'
                )
            )
        ),
        array(
            'status' => 'first-value'
        )
    );

    $client_field_list = new ClientFieldList(1, $client);
    $html = $client_field_list->render();

    assertSame(1, substr_count($html, '<p>'));
    assertSame(1, substr_count($html, '</p>'));
    assertStringContains('First field: first-value', $html);
});

$runner->test('render() preserves client field order', function () {
    $client = test_client(
        array(
            'fields' => array(
                array(
                    'label' => 'First field',
                    'field' => 'status',
                    'format' => 'display_value'
                ),
                array(
                    'label' => 'Second field',
                    'field' => 'latest_version',
                    'format' => 'display_value'
                )
            )
        ),
        array(
            'status' => 'first-value',
            'latest_version' => 'second-value'
        )
    );

    $client_field_list = new ClientFieldList(1, $client);
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
