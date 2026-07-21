#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpRoot = dirname(__DIR__);
require_once $phpRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpRoot)) . '/templates/noc/lib/compiler';
require_once $compilerRoot .'/rule.php';
require_once $compilerRoot .'/predicate.php';
require_once $compilerRoot .'/action.php';

$predicate = new Predicate(
    new FieldVal('update_available'),
    new OpVal('equals', array('string')),
    new Val(true, 'boolean')
);

$action = new Action(
    'client',
    'addNotification',
    'Software update available'
);

$rule = new Rule($predicate, $action);

assertSame($predicate, $rule->predicate());
assertSame($action, $rule->action());

assertSame('update_available', $rule->predicate()->receiver()->value());
assertSame('equals', $rule->predicate()->operator()->name());
assertSame(true, $rule->predicate()->argument()->value());

assertSame('client', $rule->action()->receiver());
assertSame('addNotification', $rule->action()->method());
assertSame('Software update available', $rule->action()->argument());

// Compiler tests
$schema = test_schema();

$ruleJson = array(
    'when' => array(
        'field' => 'update_available',
        'operator' => 'equals',
        'value' => true,
    ),
    'then' => array(
        'receiver' => 'client',
        'method' => 'addNotification',
        'argument' => 'Software update available',
    ),
);

$result = Rule::compile($ruleJson, $schema, 'Happy Path');
assertTrue($result instanceof CompilationResult);

$rule = $result->value();
assertSame('update_available', $rule->predicate()->receiver()->value());
assertSame('equals', $rule->predicate()->operator()->name());
assertSame(true, $rule->predicate()->argument()->value());
assertSame('client', $rule->action()->receiver());
assertSame('addNotification', $rule->action()->method());
assertSame('Software update available', $rule->action()->argument());

$result = RuleList::compile(array($ruleJson), $schema, 'Happy array path');
assertSame(1, count($result->value()));
assertTrue($result->value()[0] instanceof Rule);
assertSame('update_available', $result->value()[0]->predicate()->receiver()->value());

assert_compile_error(Rule::compile(42, $schema, 'rule_42'), 'rule_42 must be an object');

$result = RuleList::compile(array(), $schema, 'Empty array');
assertTrue($result instanceof CompilationResult);
assertSame(array(), $result->value());
assertSame(array(), $result->errors());
assertTrue($result->isSuccess());

$result=RuleList::compile(42, $schema, 'Number 42');
assert_compile_error($result, 'Number 42: must be an array');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'operator' => 'greaterThan',
        'value' => true,
    ),
    'then' => array(
        'receiver' => 'client',
        'method' => 'addNotification',
        'argument' => 'Software update available',
    ),
);

assert_compile_error(Rule::compile($invalidRuleJson, $schema, 'rule'), 'rule.when.operator: unsupported operator: greaterThan');
assert_compile_error(RuleList::compile(array($invalidRuleJson), $schema, 'rules'), 'rules[0].when.operator: unsupported operator: greaterThan');
assert_compile_error(RuleList::compile(array($ruleJson, $invalidRuleJson), $schema, 'rules'), 'rules[1].when.operator: unsupported operator: greaterThan');
assert_compile_error(RuleList::compile(array($ruleJson, $ruleJson, $invalidRuleJson), $schema, 'rules'), 'rules[2].when.operator: unsupported operator: greaterThan');

$invalidRuleJson = array(
    'thén' => array(
        'receiver' => 'client',
        'method' => 'notification',
        'message' => 'Software update available',
    ),
);
assert_compile_error(Rule::compile($invalidRuleJson, $schema, 'rule'), 'rule: invalid identifier: thén');

$invalidRuleJson = array(
    'then' => array(
        'receiver' => 'client',
        'method' => 'notification',
        'message' => 'Software update available',
    ),
);
assert_compile_error(Rule::compile($invalidRuleJson, $schema, 'rule'), 'rule: expected when');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
);
assert_compile_error(Rule::compile($invalidRuleJson, $schema, 'rule'), 'rule: expected then');


$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'receiver' => 'client',
        'method' => 'notification',
        'message' => 'Software update available',
    ),
    'else' => array(
        'receiver' => 'client',
        'method' => 'notification',
        'message' => 'Software update available',
    ),
);
assert_compile_error(Rule::compile($invalidRuleJson, $schema, 'rule'), 'rule: unsupported attribute: else');

