#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/client-field.php';

$runner = new TestSuiteRunner('client-field');

$runner->test('render() renders an escaped client field', function () {
    $client = test_client(
        array(
            'fields' => array(
                array(
                    'label' => 'State & mode',
                    'field' => 'status',
                    'format' => 'display_value'
                )
            )
        ),
        array('status' => '<ready> & "waiting"')
    );

    $field = $client->field_list()->fields()['status'];
    $client_field = new ClientField(1, $client, $field);
    $html = $client_field->render();

    $escaped_label = htmlspecialchars(
        $field->label()->value(),
        ENT_QUOTES,
        'UTF-8'
    );

    $escaped_value = htmlspecialchars(
        $client->get('status'),
        ENT_QUOTES,
        'UTF-8'
    );

    assertStringStartsWith(indentation(1, '<p>'), $html);
    assertStringContains(
        $escaped_label . ': ' . $escaped_value,
        $html
    );
    assertStringContains('</p>', $html);

    assertSame(1, substr_count($html, '<p>'));
    assertSame(1, substr_count($html, '</p>'));
    assertSame(0, substr_count($html, 'State & mode'));
    assertSame(0, substr_count($html, '<ready> & "waiting"'));
    assertSame(1, substr_count($html, "\n"));

    $paragraph_start = strpos($html, '<p>');
    $label_start = strpos($html, $escaped_label);
    $separator_start = strpos($html, ': ', $label_start);
    $value_start = strpos($html, $escaped_value);
    $paragraph_end = strpos($html, '</p>');

    assertTrue($paragraph_start < $label_start);
    assertTrue($label_start < $separator_start);
    assertTrue($separator_start < $value_start);
    assertTrue($value_start < $paragraph_end);
});

$runner->finish();
