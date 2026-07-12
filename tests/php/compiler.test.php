#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';

require_once $nocRoot . '/lib/rule.php';
require_once $nocRoot . '/lib/compiler.php';

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

$result = compile_rule($ruleJson, 'test');
assertTrue($result instanceof CompilationResult);
$rule = $result->rules()[0];

assertSame('update_available', $rule->predicate()->receiver());
assertSame('equals', $rule->predicate()->comparator());
assertSame(true, $rule->predicate()->argument());
assertSame('notification', $rule->effect()->type());
assertSame('Software update available', $rule->effect()->argument());

assert_compile_error(compile_rule(42, 'rule'), 'rule must be an object');

$result = compile_rules(array($ruleJson));
assertSame(1, count($result->rules()));
assertTrue($result->rules()[0] instanceof Rule);
assertSame('update_available', $result->rules()[0]->predicate()->receiver());


$result = compile_rules(array());
assertTrue($result instanceof CompilationResult);
assertSame(array(), $result->rules());
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
assert_compile_error(compile_rules(array($invalidRuleJson)), 'rules[0].when: expected exactly one supported comparator');

$invalidRuleJson = array(
    'when' => array(
        'fubar' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.when: expected field');

$invalidRuleJson = array(
    'when' => array(
        'field' => '',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.when: field must be a non-empty string');

$invalidRuleJson = array(
    'when' => array(
        'field' => 42,
        'equals' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.when: field must be a non-empty string');

$invalidRuleJson = array(
    'when' => array(
        'field' => false,
        'equals' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.when: field must be a non-empty string');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => null,
    ),
    'then' => array(
        'typo' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.equals: must be a scalar value');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'lessThan' => true,
    ),
    'then' => array(
        'typo' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.lessThan: must be numeric');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'typo' => 'notification',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: expected type');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => '',
        'message' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: type must be a non-empty string');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'massage' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: expected exactly one supported effect');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'notification',
        'value' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: notification requires message');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'health',
        'value' => 42,
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: value must be a non-empty string');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'health',
        'value' => '',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: value must be a non-empty string');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'health',
        'value' => 'hungover',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: health unsupported value: hungover');

$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'clickAction',
        'value' => 'Software update available',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: unsupported effect type clickAction');

$invalidRuleJson = array(
    'wenn' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'then' => array(
        'type' => 'health',
        'value' => 'healthy',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule: expected when');
$invalidRuleJson = array(
    'when' => array(
        'field' => 'update_available',
        'equals' => true,
    ),
    'thin' => array(
        'type' => 'health',
        'value' => 'healthy',
    ),
);

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule: expected then');

