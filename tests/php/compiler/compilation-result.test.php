#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

require_once $compilerRoot . '/compilation-result.php';

$runner = new TestRunner('CompilationResult');

$runner->test('success', function () {
    $value = new stdClass();
    $result = CompilationResult::success($value);

    assertSame(true, $result->isSuccess());
    assertSame($value, $result->value());
    assertSame(array(), $result->errors());
});

$runner->test('failure', function () {
    $errors = array(
        'first error',
        'second error',
    );
    $result = CompilationResult::failure($errors);

    assertSame(false, $result->isSuccess());
    assertSame(null, $result->value());
    assertSame($errors, $result->errors());
});

$runner->finish();
