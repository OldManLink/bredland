#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/predicate.php';
require_once $nocRoot . '/lib/compiler/compiler.php';

$predicateJson = array(
    'field' => 'update_available',
    'equals' => true,
);

$result = compile_predicate($predicateJson, 'Happy Path');
assertTrue($result instanceof CompilationResult);
$predicate = $result->value();
assertSame('update_available', $predicate->receiver());
assertSame('equals', $predicate->comparator());
assertSame(true, $predicate->argument());

$invalidPredicateJson = array(
    'field' => '',
    'equals' => true,
);
assert_compile_error(compile_predicate($invalidPredicateJson, 'rule.when'), 'rule.when: field must be a non-empty string');

$invalidPredicateJson = array(
    'field' => 42,
    'equals' => true,
);
assert_compile_error(compile_predicate($invalidPredicateJson, 'rule.when'), 'rule.when: field must be a non-empty string');

$invalidPredicateJson = array(
    'field' => false,
    'equals' => true,
);
assert_compile_error(compile_predicate($invalidPredicateJson, 'rule.when'), 'rule.when: field must be a non-empty string');

$invalidPredicateJson = array(
    'field' => 'update_available',
    'equals' => null,
);
assert_compile_error(compile_predicate($invalidPredicateJson, 'rule.when'), 'rule.when.equals: must be a scalar value');

$invalidPredicateJson = array(
    'field' => 'update_available',
    'lessThan' => true,
);
assert_compile_error(compile_predicate($invalidPredicateJson, 'rule.when'), 'rule.when.lessThan: must be numeric');
