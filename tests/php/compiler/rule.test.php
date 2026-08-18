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
$ruleJson = from_json(<<<'JSON'
{
    "when": {
        "field": "update_available",
        "operator": "equals",
        "value": true
    },
    "then": {
        "receiver": "client",
        "method": "addNotification",
        "argument": "Software update available"
    }
}
JSON
);

$runner = new TestSuiteRunner('Rule');

$runner->test('instance creation', function () {
    $predicate = new Predicate(
        new FieldVal(null, null),
        new OpVal(null, null),
        new IntVal(null)
    );
    $action = new Action(null, null, null);

    $rule = new Rule($predicate, $action);

    assertSame($predicate, $rule->predicate());
    assertSame($action, $rule->action());
});

$runner->test('compiler tests: Rule', function () use ($ruleJson) {
    $result = Rule::compile($ruleJson, test_schema(), 'Happy Path');
    assert_compile_success($result);

    $rule = $result->value();
    assertTrue($rule->predicate() instanceof Predicate, 'Predicate expected');
    assertTrue($rule->action() instanceof Action, 'Action expected');
    assertSame('update_available', $rule->predicate()->receiver()->value());
    assertSame('equals', $rule->predicate()->operator()->name());
    assertSame(true, $rule->predicate()->argument()->value());

    assertSame('client', $rule->action()->receiver()->name());
    assertSame('addNotification', $rule->action()->method()->name());
    assertSame('Software update available', $rule->action()->argument()->parts()[0]->value());

    assert_compile_error(Rule::compile(42, test_schema(), '42'), '42: must be an object');
});

$runner->test('compiler tests: RuleList', function () use ($ruleJson) {
    $result = RuleList::compile(array($ruleJson), test_schema(), 'Happy array path');
    assert_compile_success($result);
    assertSame('array', runtime_type($result->value()));
    assertSame(1, count($result->value()));
    assertTrue($result->value()[0] instanceof Rule);

    $result = RuleList::compile(array($ruleJson, $ruleJson, $ruleJson), test_schema(), 'Happy array(3) path');
    assert_compile_success($result);
    assertSame(3, count($result->value()));
    assertTrue($result->value()[2] instanceof Rule);

    $result = RuleList::compile(array(), test_schema(), 'Empty array');
    assert_compile_success($result);
    assertSame(array(), $result->value());

    $result = RuleList::compile(42, test_schema(), 'Number 42');
    assert_compile_error($result, 'Number 42: must be an array');

    $result = RuleList::compile(array(42), test_schema(), 'array(42)');
    assert_compile_error($result, 'array(42)[0]: must be an object');
});

$runner->test('unsupported operator: greaterThan', function () use ($ruleJson) {
    $invalidRuleJson = from_json(<<<'JSON'
    {
        "when": {
            "field": "update_available",
            "operator": "greaterThan",
            "value": true
        },
        "then": {
            "receiver": "client",
            "method": "addNotification",
            "argument": "Software update available"
        }
    }
JSON
    );

    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule.when.operator: unsupported operator: greaterThan');
    assert_compile_error(RuleList::compile(array($invalidRuleJson), test_schema(), 'rules'), 'rules[0].when.operator: unsupported operator: greaterThan');
    assert_compile_error(RuleList::compile(array($ruleJson, $invalidRuleJson), test_schema(), 'rules'), 'rules[1].when.operator: unsupported operator: greaterThan');
    assert_compile_error(RuleList::compile(array($ruleJson, $ruleJson, $invalidRuleJson), test_schema(), 'rules'), 'rules[2].when.operator: unsupported operator: greaterThan');
});

$runner->test('missing method noSuchMethod', function () {
    $invalidRuleJson = from_json(<<<'JSON'
    {
        "when": {
            "field": "update_available",
            "operator": "equals",
            "value": true
        },
        "then": {
            "receiver": "client",
            "method": "noSuchMethod",
            "argument": "Software update available"
        }
    }
JSON
    );

    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule.then.method: unsupported method: noSuchMethod');
});

$runner->test('invalid identifier: thén', function () {
    $invalidRuleJson = from_json(<<<'JSON'
    {
        "thén": {
            "receiver": "client",
            "method": "notification",
            "argument": "Software update available"
        }
    }
JSON
    );
    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule: invalid identifier: thén');
});

$runner->test('missing when', function () {
    $invalidRuleJson = from_json(<<<'JSON'
    {
        "then": {
            "receiver": "client",
            "method": "notification",
            "argument": "Software update available"
        }
    }
JSON
    );
    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule: expected when');
});

$runner->test('missing then', function () {
    $invalidRuleJson = from_json(<<<'JSON'
    {
        "when": {
            "field": "update_available",
            "operator": "equals",
            "value": true
        }
    }
JSON
    );
    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule: expected then');
});

$runner->test('unsupported attribute: else', function () {
    $invalidRuleJson = from_json(<<<'JSON'
    {
        "when": {
            "field": "update_available",
            "operator": "equals",
            "value": true
        },
        "then": {
            "receiver": "update_available",
            "method": "equals",
            "message": true
        },
        "else": {
            "receiver": "update_available",
            "method": "equals",
            "message": true
        }
    }
JSON
    );
    assert_compile_error(Rule::compile($invalidRuleJson, test_schema(), 'rule'), 'rule: unsupported attribute: else');
});

$runner->finish();