#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';
require_once $nocRoot . '/lib/option.php';

$runner = new TestSuiteRunner('option');

$runner->test('map() preserves absence and transforms presence', function () {
    $none = Option::none();
    $some = Option::some(40);

    $mappedNone = $none->map(function ($value) {
        return $value + 2;
    });

    $mappedSome = $some->map(function ($value) {
        return $value + 2;
    });

    assertSame(array(), $mappedNone->values());
    assertSame(array(42), $mappedSome->values());
});

$runner->test('get_or_else() preserves presence and evaluates fallback for absence', function () {
    $none = Option::none();
    $some = Option::some(42);

    assertSame(
        99,
        $none->get_or_else(function () {
            return 99;
        })
    );

    assertSame(
        42,
        $some->get_or_else(function () {
            return 99;
        })
    );
});

$runner->finish();
