#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpRoot = dirname(__DIR__);
require_once $phpRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpRoot)) . '/templates/noc/lib/compiler';
require_once $compilerRoot .'/rule.php';
require_once $compilerRoot .'/predicate.php';
require_once $compilerRoot .'/action.php';

// Correctly constructed Rule in json format
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

$runner = new TestRunner('Rule');

$runner->test('instance creation', function () {
    $predicate = new Predicate(
        new FieldVal(null),
        new OpVal(null, null),
        new Val(null, null)
    );
    $action = new Action(null, null, null);

    $rule = new Rule($predicate, $action);

    assertSame($predicate, $rule->predicate());
    assertSame($action, $rule->action());
});

$runner->test('compiler tests: Rule', function () use ($ruleJson) {
    $result = Rule::compile($ruleJson, test_schema(), 'Happy Path');
    assert_compile_success($result);

    assertTrue($result->value()->predicate() instanceof Predicate, 'Predicate expected');
    assertTrue($result->value()->action() instanceof Action, 'Action expected');

    assert_compile_error(Rule::compile(42, $schema, 'rule_42'), 'rule_42 must be an object');
});

$runner->test('compiler tests: RuleList', function () use ($ruleJson) {
    $schema = test_schema();

    $result = RuleList::compile(array($ruleJson), $schema, 'Happy array path');
    assert_compile_success($result);
    assertSame(runtime_type($result->value()), 'array');
    assertSame(1, count($result->value()));
    assertTrue($result->value()[0] instanceof Rule);

    $result = RuleList::compile(array($ruleJson, $ruleJson, $ruleJson), $schema, 'Happy array(3) path');
    assert_compile_success($result);
    assertSame(3, count($result->value()));
    assertTrue($result->value()[2] instanceof Rule);

    $result = RuleList::compile(array(), $schema, 'Empty array');
    assert_compile_success($result);
    assertSame($result->value(), array());

    $result=RuleList::compile(42, $schema, 'Number 42');
    assert_compile_error($result, 'Number 42: must be an array');
});

$runner->test('unsupported operator: greaterThan', function () use ($ruleJson) {
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

    $schema = test_schema();
    assert_compile_error(Rule::compile($invalidRuleJson, $schema, 'rule'), 'rule.when.operator: unsupported operator: greaterThan');
    assert_compile_error(RuleList::compile(array($invalidRuleJson), $schema, 'rules'), 'rules[0].when.operator: unsupported operator: greaterThan');
    assert_compile_error(RuleList::compile(array($ruleJson, $invalidRuleJson), $schema, 'rules'), 'rules[1].when.operator: unsupported operator: greaterThan');
    assert_compile_error(RuleList::compile(array($ruleJson, $ruleJson, $invalidRuleJson), $schema, 'rules'), 'rules[2].when.operator: unsupported operator: greaterThan');
});

$runner->test('invalid identifier: thén', function () {
    $invalidRuleJson = array(
        'thén' => array(
            'receiver' => 'client',
            'method' => 'notification',
            'message' => 'Software update available',
        ),
    );
    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule: invalid identifier: thén');
});

$runner->test('missing when', function () {
    $invalidRuleJson = array(
        'then' => array(
            'receiver' => 'client',
            'method' => 'notification',
            'message' => 'Software update available',
        ),
    );
    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule: expected when');
});

$runner->test('missing then', function () {
    $invalidRuleJson = array(
        'when' => array(
            'field' => 'update_available',
            'operator' => 'equals',
            'value' => true,
        ),
    );
    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule: expected then');
});

$runner->test('unsupported attribute: else', function () {
    $invalidRuleJson = array(
        'when' => array(
            'field' => 'update_available',
            'operator' => 'equals',
            'value' => true,
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
    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule: unsupported attribute: else');
});

$runner->finish();