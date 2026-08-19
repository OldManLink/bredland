#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
require_once dirname(dirname(__DIR__)) . '/templates/noc/lib/record-builder.php';

$runner = new TestSuiteRunner('record-builder');

$runner->test('builds typed record from schema and source', function () {
    $builder = new RecordBuilder();

    $record = $builder->build(
        array(
            'schema' => array('const' => 1),
            'host' => array('const' => 'bredland'),
            'uptime' => array('value_type' => 'integer'),
            'temperature' => array('value_type' => 'float'),
            'remote_addr' => array('value_type' => 'string')
        ),
        array(
            'uptime' => '12345',
            'temperature' => '47.2',
            'remote_addr' => '127.0.0.1'
        )
    );

    assertSame(
        array(
            'schema' => 1,
            'host' => 'bredland',
            'uptime' => 12345,
            'temperature' => 47.2,
            'remote_addr' => '127.0.0.1'
        ),
        $record
    );
});

$runner->test('rejects missing required field', function () {
    $builder = new RecordBuilder();

    try {
        $builder->build(
            array(
                'uptime' => array(
                    'value_type' => 'integer'
                )
            ),
            array()
        );

        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'missing field: uptime',
            $e->getMessage()
        );
    }
});

$runner->test('rejects invalid integer value', function () {
    $builder = new RecordBuilder();

    try {
        $builder->build(
            array(
                'uptime' => array(
                    'value_type' => 'integer'
                )
            ),
            array(
                'uptime' => '12.3'
            )
        );

        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'invalid value for field uptime: expected integer',
            $e->getMessage()
        );
    }
});

$runner->test('rejects invalid float value', function () {
    $builder = new RecordBuilder();

    try {
        $builder->build(
            array(
                'temperature' => array(
                    'value_type' => 'float'
                )
            ),
            array(
                'temperature' => 'not-a-number'
            )
        );

        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'invalid value for field temperature: expected float',
            $e->getMessage()
        );
    }
});

$runner->test('accepts integer-looking value as float', function () {
    $builder = new RecordBuilder();

    $record = $builder->build(
        array(
            'temperature' => array(
                'value_type' => 'float'
            )
        ),
        array(
            'temperature' => '42'
        )
    );

    assertSame(
        array(
            'temperature' => 42.0
        ),
        $record
    );
});

$runner->test('converts boolean values', function () {
    $builder = new RecordBuilder();

    assertSame(
        array('healthy' => true),
        $builder->build(
            array(
                'healthy' => array(
                    'value_type' => 'boolean'
                )
            ),
            array(
                'healthy' => 'true'
            )
        )
    );

    assertSame(
        array('healthy' => false),
        $builder->build(
            array(
                'healthy' => array(
                    'value_type' => 'boolean'
                )
            ),
            array(
                'healthy' => 'false'
            )
        )
    );
});

$runner->test('rejects invalid boolean value', function () {
    $builder = new RecordBuilder();

    try {
        $builder->build(
            array(
                'healthy' => array(
                    'value_type' => 'boolean'
                )
            ),
            array(
                'healthy' => '1'
            )
        );

        fail('expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        assertSame(
            'invalid value for field healthy: expected boolean',
            $e->getMessage()
        );
    }
});

$runner->test('preserves string values', function () {
    $builder = new RecordBuilder();

    $record = $builder->build(
        array(
            'version' => array(
                'value_type' => 'string'
            )
        ),
        array(
            'version' => '7.23.1'
        )
    );

    assertSame(
        array(
            'version' => '7.23.1'
        ),
        $record
    );
});

$runner->finish();
