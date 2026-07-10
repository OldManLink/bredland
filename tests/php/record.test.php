#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';
$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/record.php';

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

assertSame(
    array(
        'temperature' => '35.5',
    ),
    select_fields(' temperature ', $source)
);

$schemasDir = $nocRoot . '/schemas';

$schema = load_record_schema($schemasDir, 'bredland');

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
        'remote_addr' =>
        array (
          'valueType' => 'string',
        ),
    ),
    $schema
);

try {
    load_record_schema($schemasDir, 'missing');

    fail('Expected missing record schema to be rejected');
} catch (InvalidArgumentException $e) {
    assertSame(
        'missing record schema: missing',
        $e->getMessage()
    );
}

$tmpSchemasDir = sys_get_temp_dir() . '/schemas-' . uniqid();
mkdir($tmpSchemasDir);

file_put_contents(
    $tmpSchemasDir . '/broken.json',
    '{ this is not json'
);

try {
    load_record_schema($tmpSchemasDir, 'broken');

    fail('Expected malformed record schema to be rejected');
} catch (InvalidArgumentException $e) {
    assertSame(
        'invalid record schema: broken',
        $e->getMessage()
    );
}

unlink($tmpSchemasDir . '/broken.json');
rmdir($tmpSchemasDir);

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

$record = build_record(
    $schema,
    array(
        'ts' => '2026-07-01T15:00:00Z',
        'temperature' => '35.5',
        'throttled' => '0x0',
    )
);

assertSame(
    array(
        'schema' => TELEMETRY_SCHEMA_VERSION,
        'ts' => '2026-07-01T15:00:00Z',
        'host' => 'bredland',
        'temperature' => 35.5,
        'throttled' => '0x0',
    ),
    $record
);

try {
    build_record(
        $schema,
        array(
            'ts' => '2026-07-01T15:00:00Z',
            'temperature' => '35.5°C',
            'throttled' => '0x0',
        )
    );

    fail('Expected invalid float field to be rejected');
} catch (InvalidArgumentException $e) {
    assertSame(
        'invalid value for field temperature: expected float',
        $e->getMessage()
    );
}

try {
    build_record(
        $schema,
        array(
            'ts' => '2026-07-01T15:00:00Z',
            'temperature' => '35.5',
        )
    );

    fail('Expected missing schema field to be rejected');
} catch (InvalidArgumentException $e) {
    assertSame(
        'missing field: throttled',
        $e->getMessage()
    );
}

foreach (reserved_fields() as $reserved_field)
{
    try {
        select_fields($reserved_field, array("$reserved_field" => 'reject me'));
        fail("Expected $reserved_field to be rejected");
    } catch (InvalidArgumentException $e) {
        assertSame("reserved field: $reserved_field", $e->getMessage());
    }
}

assertSame(123, convert_field_value('123', 'integer'));
assertSame(-123, convert_field_value('-123', 'integer'));
assertSame(null, convert_field_value('123x', 'integer'));
assertSame(null, convert_field_value('', 'integer'));

assertSame(42.0, convert_field_value('42.0', 'float'));
assertSame(-42.0, convert_field_value('-42.0', 'float'));
assertSame(0.42, convert_field_value('.42', 'float'));
assertSame(-0.42, convert_field_value('-.42', 'float'));
assertSame(42.0, convert_field_value('42', 'float'));
assertSame(null, convert_field_value('42.0°C', 'float'));
assertSame(null, convert_field_value('', 'float'));

assertSame('123', convert_field_value('123', 'string'));
