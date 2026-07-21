#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/action.php';

$action = new Action(
    'client',
    'addNotification',
    'Software update available'
);

assertSame('client', $action->receiver());
assertSame('addNotification', $action->method());
assertSame('Software update available', $action->argument());

// Compiler tests
$schema = test_schema();

$actionJson = array(
    'receiver' => 'client',
    'method' => 'addNotification',
    'argument' => 'Software update available.',
);
$result = Action::compile($actionJson, $schema, 'Happy addNotification path');
assertTrue($result instanceof CompilationResult);
assertTrue($result->isSuccess());
$action = $result->value();
assertSame('client', $action->receiver());
assertSame('addNotification', $action->method());
assertSame('Software update available.', $action->argument());

$actionJson = array(
    'receiver' => 'client',
    'method' => 'setHealth',
    'argument' => 'healthy',
);
$result = Action::compile($actionJson, $schema, 'Happy setHealth path');
assertTrue($result instanceof CompilationResult);
assertTrue($result->isSuccess());
$action = $result->value();
assertSame('client', $action->receiver());
assertSame('setHealth', $action->method());
assertSame('healthy', $action->argument());

$invalidActionJson = array(
    'receiver' => 'client',
    'argument' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: expected method');

$invalidActionJson = array(
    'receiver' => 'noc',
    'method' => 'addNotification',
    'fubar' => 'message',
    'argument' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: unsupported attribute: fubar');

$invalidActionJson = array(
    'receiver' => 'noc',
    'method' => 'addNotification',
    'methöd' => 'addNotification',
    'argument' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: invalid identifier: methöd');

$invalidActionJson = array(
    'receiver' => 'client',
    'method' => '',
    'argument' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then.method: must be a non-empty string');

$invalidActionJson = array(
    'receiver' => 'noc',
    'method' => 'clickAction',
    'argument' => 'clicked',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: unsupported method clickAction');

$invalidActionJson = array(
    'receiver' => 'client',
    'method' => 'addNotification',
    'argyment' => 'Software update available.',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then: unsupported attribute: argyment');

$invalidActionJson = array(
    'receiver' => 'client',
    'method' => 'setHealth',
    'argument' => 42,
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then.argument: must be a non-empty string');

$invalidActionJson = array(
    'receiver' => 'client',
    'method' => 'setHealth',
    'argument' => '',
);

assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then.argument: must be a non-empty string');

$invalidActionJson = array(
    'receiver' => 'client',
    'method' => 'setHealth',
    'argument' => 'hungover',
);
assert_compile_error(Action::compile($invalidActionJson, $schema, 'rule.then'), 'rule.then.setHealth: unsupported argument hungover');
