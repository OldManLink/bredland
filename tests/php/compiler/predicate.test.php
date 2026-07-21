#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/predicate.php';

$predicate = new Predicate(
    new FieldVal('update_available'),
    new OpVal('equals', array('string')),
    new Val(true, 'boolean')
);

assertSame('update_available', $predicate->receiver()->value());
assertSame('equals', $predicate->operator()->name());
assertSame(true, $predicate->argument()->value());

// Compiler tests
$schema = test_schema();

$predicateJson = array(
    'field' => 'update_available',
    'operator' => 'equals',
    'value' => true,
);

$result = Predicate::compile($predicateJson, $schema, 'Happy Path');
assertTrue($result instanceof CompilationResult);
$predicate = $result->value();
assertSame('update_available', $predicate->receiver()->value());
assertSame('equals', $predicate->operator()->name());
assertSame(true, $predicate->argument()->value());

$invalidPredicateJson = array(
    'operator' => 'equals',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when: expected field');

$invalidPredicateJson = array(
    'feild' => '',
    'operator' => 'equals',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when: unsupported attribute: feild');

$invalidPredicateJson = array(
    'field' => 'uptime',
    'operator' => 'equals',
    'fubar' => array(),
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when: unsupported attribute: fubar');

$invalidPredicateJson = array(
    'field' => 'uptime',
    'operator' => 'equals',
    'fübar' => array(),
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when: invalid identifier: fübar');

$invalidPredicateJson = array(
    'field' => '',
    'operator' => 'equals',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when.field: must be a non-empty string');

$invalidPredicateJson = array(
    'field' => 42,
    'operator' => 'equals',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when.field: must be a non-empty string');

$invalidPredicateJson = array(
    'field' => false,
    'operator' => 'equals',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when.field: must be a non-empty string');

$invalidPredicateJson = array(
    'field' => 'update_available',
    'operator' => 'equals',
    'value' => null,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when.value: must not be undefined');

$invalidPredicateJson = array(
    'field' => 'update_available',
    'operator' => 'equals',
    'value' => array(),
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when.equals: array incompatible with boolean');

$invalidPredicateJson = array(
    'field' => 'update_available',
    'operator' => 'lessThan',
    'value' => true,
);
assert_compile_error(Predicate::compile($invalidPredicateJson, $schema, 'rule.when'), 'rule.when.lessThan: incompatible with boolean');
