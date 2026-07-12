#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/rule.php';
require_once $nocRoot . '/lib/compiler/compiler.php';

$ruleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

$result = compile_rule($ruleJson, 'Happy Path');
assertTrue($result instanceof CompilationResult);

$rule = $result->value();
assertSame('update_available', $rule->predicate()->receiver());
assertSame('equals', $rule->predicate()->comparator());
assertSame(true, $rule->predicate()->argument());
assertSame('notification', $rule->effect()->type());
assertSame('Software update available', $rule->effect()->argument());

$result = compile_rules(array($ruleJson));
assertSame(1, count($result->value()));
assertTrue($result->value()[0] instanceof Rule);
assertSame('update_available', $result->value()[0]->predicate()->receiver());

assert_compile_error(compile_rule(42, 'rule_42'), 'rule_42 must be an object');

$result = compile_rules(array());
assertTrue($result instanceof CompilationResult);
assertSame(array(), $result->value());
assertSame(array(), $result->messages());
assertFalse($result->hasErrors());

assert_compile_error(compile_rules(42), 'rules must be an array');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'greaterThan' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.when: expected exactly one supported comparator');
assert_includes_compile_error(compile_rules(array($invalidRuleJson)), 'rules[0].when: expected exactly one supported comparator');
assert_includes_compile_error(compile_rules(array($ruleJson, $invalidRuleJson)), 'rules[1].when: expected exactly one supported comparator');
assert_includes_compile_error(compile_rules(array($ruleJson, $ruleJson, $invalidRuleJson)), 'rules[2].when: expected exactly one supported comparator');

$invalidRuleJson = array(
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule: expected when');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule: expected then');


$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
    'else' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule: unsupported attribute else');

