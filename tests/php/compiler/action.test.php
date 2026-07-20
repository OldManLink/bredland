#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/action.php';

$action = new Action(
    'addNotification',
    'Software update available'
);

assertSame('addNotification', $action->method());
assertSame('Software update available', $action->argument());

// Compiler tests
$schema = test_schema();

$actionJson = array(
    'method' => 'addNotification',
    'argument' => 'Software update available.',
);
$result = Action::compile($actionJson, $schema, 'Happy addNotification path');
assertTrue($result instanceof CompilationResult);
assertTrue($result->isSuccess());
$action = $result->value();
assertSame('addNotification', $action->method());
assertSame('Software update available.', $action->argument());

$actionJson = array(
    'method' => 'setHealth',
    'argument' => 'healthy',
);
$result = Action::compile($actionJson, $schema, 'Happy setHealth path');
assertTrue($result instanceof CompilationResult);
assertTrue($result->isSuccess());
$action = $result->value();
assertSame('setHealth', $action->method());
assertSame('healthy', $action->argument());

$invalidActionJson = array(
    'argument' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: expected method');

$invalidActionJson = array(
    'method' => 'addNotification',
    'fubar' => 'message',
    'argument' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: unsupported attribute: fubar');

$invalidActionJson = array(
    'method' => 'addNotification',
    'methöd' => 'addNotification',
    'argument' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: invalid identifier: methöd');

$invalidActionJson = array(
    'method' => '',
    'argument' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then.method: must be a non-empty string');

$invalidActionJson = array(
    'method' => 'clickAction',
    'argument' => 'clicked',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: unsupported method clickAction');

$invalidActionJson = array(
    'method' => 'addNotification',
    'argyment' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: unsupported attribute: argyment');

$invalidActionJson = array(
    'method' => 'setHealth',
    'argument' => 42,
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then.argument: must be a non-empty string');

$invalidActionJson = array(
    'method' => 'setHealth',
    'argument' => '',
);

assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then.argument: must be a non-empty string');

$invalidActionJson = array(
    'method' => 'setHealth',
    'argument' => 'hungover',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then.setHealth: unsupported argument hungover');
