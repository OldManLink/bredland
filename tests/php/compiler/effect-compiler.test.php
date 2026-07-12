#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/effect.php';
require_once $nocRoot . '/lib/compiler/compiler.php';


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

assert_compile_error(compile_rule($invalidRuleJson, 'rule'), 'rule.then: notification requires message');

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

