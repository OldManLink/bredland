<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/slot-val.php';

$schema = test_schema();
// Happy path no placeholder
$result = SlotVal::compile(
    'Software update available',
    $schema,
    'message'
);

assertTrue($result instanceof CompilationResult);
assertTrue($result->isSuccess());

$slotVal = $result->value();

assertSame(1, count($slotVal->parts()));
assertTrue($slotVal->parts()[0] instanceof StrVal);
assertSame(
    'Software update available',
    $slotVal->parts()[0]->value()
);

// Happy path only placeholder
$result = SlotVal::compile(
    '{{latest_version}}',
    $schema,
    'message'
);

assertTrue($result instanceof CompilationResult);
assertTrue($result->isSuccess());

$slotVal = $result->value();

assertSame(1, count($slotVal->parts()));
assertTrue($slotVal->parts()[0] instanceof FieldVal);
assertSame(
    'latest_version',
    $slotVal->parts()[0]->value()
);

// Bad placeholder only
$result = SlotVal::compile(
    '{{no_such_field}}',
    $schema,
    'message'
);

assert_compile_error($result, "message[0]: 'no_such_field' must exist in schema");
