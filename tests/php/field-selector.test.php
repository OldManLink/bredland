#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/field-selector.php';

$runner = new TestSuiteRunner('field-selector');

$runner->test('selects requested fields', function () {
    $selector = new FieldSelector();

    $selected = $selector->select(
        'temperature,free_memory',
        array(
            'temperature' => ' 47.2 ',
            'free_memory' => ' 123456789 ',
            'ignored' => 'value'
        )
    );

    assertSame(
        array(
            'temperature' => '47.2',
            'free_memory' => '123456789'
        ),
        $selected
    );
});

$runner->test('rejects invalid field name', function () {
    $selector = new FieldSelector();

    try {
        $selector->select(
            'temperature,bad-field',
            array(
                'temperature' => '47.2',
                'bad-field' => '123'
            )
        );

        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'invalid field name: bad-field',
            $e->getMessage()
        );
    }
});

$runner->test('rejects missing requested field', function () {
    $selector = new FieldSelector();

    try {
        $selector->select(
            'temperature,free_memory',
            array(
                'temperature' => '47.2'
            )
        );

        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'missing field: free_memory',
            $e->getMessage()
        );
    }
});

$runner->test('rejects reserved field', function () {
    $selector = new FieldSelector();

    try {
        $selector->select(
            'temperature,ts',
            array(
                'temperature' => '47.2',
                'ts' => 'fake'
            )
        );

        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'reserved field: ts',
            $e->getMessage()
        );
    }
});

$runner->test('ignores blank field entries', function () {
    $selector = new FieldSelector();

    $selected = $selector->select(
        'temperature, ,free_memory,,',
        array(
            'temperature' => '47.2',
            'free_memory' => '123456789'
        )
    );

    assertSame(
        array(
            'temperature' => '47.2',
            'free_memory' => '123456789'
        ),
        $selected
    );
});

$runner->test('rejects all reserved fields', function () {
    $selector = new FieldSelector();

    $reservedFields = array(
        'schema',
        'ts',
        'host',
        'ttl',
        'token',
        'uptime',
        'fields',
        'remote_addr'
    );

    foreach ($reservedFields as $reservedField) {
        try {
            $selector->select(
                $reservedField,
                array(
                    $reservedField => 'reject me'
                )
            );

            fail(
                "expected reserved field $reservedField to be rejected"
            );
        } catch (InvalidArgumentException $e) {
            assertSame(
                "reserved field: $reservedField",
                $e->getMessage()
            );
        }
    }
});

$runner->finish();
