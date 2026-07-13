#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/predicate.php';

$predicate = new Predicate(
    'update_available',
    'equals',
    true
);

assertSame('update_available', $predicate->receiver());
assertSame('equals', $predicate->operator());
assertSame(true, $predicate->argument());

// Compiler tests

$predicateJson = array(
    'field' => 'update_available',
    'operator' => 'equals',
    'value' => true,
);

$result = Predicate::compile($predicateJson, 'Happy Path');
assertTrue($result instanceof CompilationResult);
$predicate = $result->value();
assertSame('update_available', $predicate->receiver());
assertSame('equals', $predicate->operator());
assertSame(true, $predicate->argument());

$invalidPredicateJson = array(
    'field' => '',
    'operator' => 'equals',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, 'rule.when'), 'rule.when: field must be a non-empty string');

$invalidPredicateJson = array(
    'field' => 42,
    'operator' => 'equals',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, 'rule.when'), 'rule.when: field must be a non-empty string');

$invalidPredicateJson = array(
    'field' => false,
    'operator' => 'equals',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, 'rule.when'), 'rule.when: field must be a non-empty string');

$invalidPredicateJson = array(
    'field' => 'update_available',
    'operator' => 'equals',
    'value' => null,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, 'rule.when'), 'rule.when.equals: must be a scalar value');

$invalidPredicateJson = array(
    'field' => 'update_available',
    'operator' => 'lessThan',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, 'rule.when'), 'rule.when.lessThan: must be numeric');
