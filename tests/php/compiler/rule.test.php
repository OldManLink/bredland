#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpRoot = dirname(__DIR__);
require_once $phpRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpRoot)) . '/templates/noc/lib/compiler';
require_once $compilerRoot .'/rule.php';
require_once $compilerRoot .'/predicate.php';
require_once $compilerRoot .'/effect.php';

$predicate = new Predicate(
    'update_available',
    'equals',
    true
);

$effect = new Effect(
    'notification',
    'message',
    'Software update available'
);

$rule = new Rule($predicate, $effect);

assertSame($predicate, $rule->predicate());
assertSame($effect, $rule->effect());

assertSame('update_available', $rule->predicate()->receiver());
assertSame('equals', $rule->predicate()->operator());
assertSame(true, $rule->predicate()->argument());

assertSame('notification', $rule->effect()->receiver());
assertSame('message', $rule->effect()->attribute());
assertSame('Software update available', $rule->effect()->argument());

// Compiler tests

$ruleJson = array(
    'when' => array(
        'field' => 'update_available',
        'operator' => 'equals',
        'value' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'attribute' => 'message',
        'value' => 'Software update available',
    ),
);

$result = Rule::compile($ruleJson, 'Happy Path');
assertTrue($result instanceof CompilationResult);

$rule = $result->value();
assertSame('update_available', $rule->predicate()->receiver());
assertSame('equals', $rule->predicate()->operator());
assertSame(true, $rule->predicate()->argument());
assertSame('notification', $rule->effect()->receiver());
assertSame('message', $rule->effect()->attribute());
assertSame('Software update available', $rule->effect()->argument());

$result = RuleList::compile(array($ruleJson), 'Happy array path');
assertSame(1, count($result->value()));
assertTrue($result->value()[0] instanceof Rule);
assertSame('update_available', $result->value()[0]->predicate()->receiver());

assert_compile_error(Rule::compile(42, 'rule_42'), 'rule_42 must be an object');

$result = RuleList::compile(array(), 'Empty array');
assertTrue($result instanceof CompilationResult);
assertSame(array(), $result->value());
assertSame(array(), $result->errors());
assertTrue($result->isSuccess());

$result=RuleList::compile(42, 'Number 42');
assert_compile_error($result, 'Number 42: must be an array');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'operator' => 'greaterThan',
        'value' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'attribute' => 'message',
        'value' => 'Software update available',
    ),
);

assert_compile_error(Rule::compile($invalidRuleJson, 'rule'), 'rule.when: unsupported operator: greaterThan');
assert_compile_error(RuleList::compile(array($invalidRuleJson), 'rules'), 'rules[0].when: unsupported operator: greaterThan');
assert_compile_error(RuleList::compile(array($ruleJson, $invalidRuleJson), 'rules'), 'rules[1].when: unsupported operator: greaterThan');
assert_compile_error(RuleList::compile(array($ruleJson, $ruleJson, $invalidRuleJson), 'rules'), 'rules[2].when: unsupported operator: greaterThan');

$invalidRuleJson = array(
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(Rule::compile($invalidRuleJson, 'rule'), 'rule: expected when');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
);

assert_compile_error(Rule::compile($invalidRuleJson, 'rule'), 'rule: expected then');


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

$result = Rule::compile($invalidRuleJson, 'rule');
assert_compile_error($result, 'rule: unsupported attribute else');

