#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$runner = new TestSuiteRunner('test-suite-runner');

$runner->test('skips an individual test and continues the suite', function () {
    assertTrue(
        method_exists('TestSuiteRunner', 'skip'),
        'TestSuiteRunner::skip() expected'
    );

    $continued = false;
    $skippedTestRan = false;

    $nestedRunner = new TestSuiteRunner('nested');

    $nestedRunner->test('before skip', function () {
        assertTrue(true);
    });

    $nestedRunner->skip(
        'temporarily skipped test',
        'Waiting for the new implementation',
        function () use (&$skippedTestRan) {
            $skippedTestRan = true;
        }
    );

    $nestedRunner->test('after skip', function () use (&$continued) {
        $continued = true;
        assertTrue(true);
    });

    assertFalse(
        $skippedTestRan,
        'Skipped test body must not run'
    );

    assertTrue(
        $continued,
        'Suite should continue after a skipped test'
    );

    $nestedRunner->finish();
});

$runner->finish();