#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/lib/testlib.php';
require __DIR__ . '/../../templates/oderland/telemetry.config.template.php';
require __DIR__ . '/../../templates/oderland/lib/record.php';

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

$record = build_record(
    TELEMETRY_SCHEMA_VERSION,
    '2026-07-01T15:00:00Z',
    'bredland',
    array(
        'temperature' => '35.5',
        'throttled' => '0x0',
    )
);

assertSame(
    array(
        'schema' => TELEMETRY_SCHEMA_VERSION,
        'ts' => '2026-07-01T15:00:00Z',
        'host' => 'bredland',
        'temperature' => '35.5',
        'throttled' => '0x0',
    ),
    $record
);

foreach (RESERVED_FIELDS as $RESERVED_FIELD)
{
    try {
        select_fields($RESERVED_FIELD, array("$RESERVED_FIELD" => 'reject me'));
        fail("Expected $RESERVED_FIELD to be rejected");
    } catch (InvalidArgumentException $e) {
        assertSame("reserved field: $RESERVED_FIELD", $e->getMessage());
    }
}

echo "record tests passed\n";
