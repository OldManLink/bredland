#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/record.php';

$runner = new TestRunner('record');

$runner->test('selects requested fields', function () {
    $source = array(
        'temperature' => ' 35.5 ',
        'throttled' => '0x0',
        'ignored' => 'nope',
    );

    assertSame(
        array(
            'temperature' => '35.5',
            'throttled' => '0x0',
        ),
        select_fields('temperature,throttled', $source)
    );
});

$runner->test('trims requested field names', function () {
    $source = array(
        'temperature' => ' 35.5 ',
        'throttled' => '0x0',
        'ignored' => 'nope',
    );

    assertSame(
        array(
            'temperature' => '35.5',
        ),
        select_fields(' temperature ', $source)
    );
});

$runner->test('loads a record schema', function () use ($nocRoot) {
    $schema = load_record_schema($nocRoot . '/schemas', 'bredland');

    assertSame(
        array(
            'schema' => array(
                'const' => TELEMETRY_SCHEMA_VERSION,
            ),
            'ts' => array(
                'valueType' => 'string',
            ),
            'host' => array(
                'const' => 'bredland',
            ),
            'uptime' => array(
                'valueType' => 'integer',
            ),
            'temperature' => array(
                'valueType' => 'float',
            ),
            'throttled' => array(
                'valueType' => 'string',
            ),
            'free_memory' => array(
                'valueType' => 'integer',
            ),
            'total_memory' => array(
                'valueType' => 'integer',
            ),
            'root_free' => array(
                'valueType' => 'integer',
            ),
            'root_total' => array(
                'valueType' => 'integer',
            ),
            'remote_addr' => array(
                'valueType' => 'string',
            ),
        ),
        $schema
    );
});

$runner->test('rejects a missing record schema', function () use ($nocRoot) {
    assertThrows(
        'InvalidArgumentException',
        'missing record schema: missing',
        function () use ($nocRoot) {
            load_record_schema($nocRoot . '/schemas', 'missing');
        }
    );
});

$runner->test('rejects a malformed record schema', function () {
    $schemasDir = sys_get_temp_dir() . '/schemas-' . uniqid();
    $schemaFile = $schemasDir . '/broken.json';

    mkdir($schemasDir);
    file_put_contents($schemaFile, '{ this is not json');

    assertThrows(
        'InvalidArgumentException',
        'invalid record schema: broken',
        function () use ($schemasDir) {
            load_record_schema($schemasDir, 'broken');
        }
    );

    unlink($schemaFile);
    rmdir($schemasDir);
});

$runner->test('builds a typed record', function () {
    $schema = array(
        'schema' => array(
            'const' => TELEMETRY_SCHEMA_VERSION,
        ),
        'ts' => array(
            'valueType' => 'string',
        ),
        'host' => array(
            'const' => 'bredland',
        ),
        'temperature' => array(
            'valueType' => 'float',
        ),
        'throttled' => array(
            'valueType' => 'string',
        ),
        'update_available' => array(
            'valueType' => 'boolean',
        ),
    );

    $record = build_record(
        $schema,
        array(
            'ts' => '2026-07-01T15:00:00Z',
            'temperature' => '35.5',
            'throttled' => '0x0',
            'update_available' => 'false',
        )
    );

    assertSame(
        array(
            'schema' => TELEMETRY_SCHEMA_VERSION,
            'ts' => '2026-07-01T15:00:00Z',
            'host' => 'bredland',
            'temperature' => 35.5,
            'throttled' => '0x0',
            'update_available' => false,
        ),
        $record
    );
});

$runner->test('rejects an invalid float field', function () {
    $schema = array(
        'schema' => array(
            'const' => TELEMETRY_SCHEMA_VERSION,
        ),
        'ts' => array(
            'valueType' => 'string',
        ),
        'host' => array(
            'const' => 'bredland',
        ),
        'temperature' => array(
            'valueType' => 'float',
        ),
        'throttled' => array(
            'valueType' => 'string',
        ),
    );

    assertThrows(
        'InvalidArgumentException',
        'invalid value for field temperature: expected float',
        function () use ($schema) {
            build_record(
                $schema,
                array(
                    'ts' => '2026-07-01T15:00:00Z',
                    'temperature' => '35.5°C',
                    'throttled' => '0x0',
                )
            );
        }
    );
});

$runner->test('rejects a missing schema field', function () {
    $schema = array(
        'schema' => array(
            'const' => TELEMETRY_SCHEMA_VERSION,
        ),
        'ts' => array(
            'valueType' => 'string',
        ),
        'host' => array(
            'const' => 'bredland',
        ),
        'temperature' => array(
            'valueType' => 'float',
        ),
        'throttled' => array(
            'valueType' => 'string',
        ),
    );

    assertThrows(
        'InvalidArgumentException',
        'missing field: throttled',
        function () use ($schema) {
            build_record(
                $schema,
                array(
                    'ts' => '2026-07-01T15:00:00Z',
                    'temperature' => '35.5',
                )
            );
        }
    );
});

$runner->test('rejects reserved fields', function () {
    foreach (reserved_fields() as $reservedField) {
        assertThrows(
            'InvalidArgumentException',
            "reserved field: $reservedField",
            function () use ($reservedField) {
                select_fields(
                    $reservedField,
                    array($reservedField => 'reject me')
                );
            }
        );
    }
});

$runner->test('converts integer values', function () {
    assertSame(123, convert_field_value('123', 'integer'));
    assertSame(-123, convert_field_value('-123', 'integer'));
    assertSame(null, convert_field_value('123x', 'integer'));
    assertSame(null, convert_field_value('', 'integer'));
});

$runner->test('converts float values', function () {
    assertSame(42.0, convert_field_value('42.0', 'float'));
    assertSame(-42.0, convert_field_value('-42.0', 'float'));
    assertSame(0.42, convert_field_value('.42', 'float'));
    assertSame(-0.42, convert_field_value('-.42', 'float'));
    assertSame(42.0, convert_field_value('42', 'float'));
    assertSame(null, convert_field_value('42.0°C', 'float'));
    assertSame(null, convert_field_value('', 'float'));
});

$runner->test('converts boolean values', function () {
    assertSame(true, convert_field_value('true', 'boolean'));
    assertSame(false, convert_field_value('false', 'boolean'));

    assertSame(null, convert_field_value('1', 'boolean'));
    assertSame(null, convert_field_value('0', 'boolean'));
    assertSame(null, convert_field_value('yes', 'boolean'));
    assertSame(null, convert_field_value('', 'boolean'));
});

$runner->test('converts string values', function () {
    assertSame('123', convert_field_value('123', 'string'));
});

$runner->finish();
