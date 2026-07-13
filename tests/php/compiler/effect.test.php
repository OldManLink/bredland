#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$nocRoot = dirname(dirname($phpTestRoot)) . '/templates/noc';

require_once $nocRoot . '/lib/compiler/effect.php';

$effect = new Effect(
    'notification',
    'message',
    'Software update available'
);

assertSame('notification', $effect->receiver());
assertSame('message', $effect->attribute());
assertSame('Software update available', $effect->argument());

// Compiler tests

$effectJson = array(
    'type' => 'notification',
    'attribute' => 'message',
    'value' => 'Software update available.',
);

$result = Effect::compile($effectJson, 'Happy Path');
assertTrue($result instanceof CompilationResult);
assertTrue($result->isSuccess());
$effect = $result->value();
assertSame('notification', $effect->receiver());
assertSame('message', $effect->attribute());
assertSame('Software update available.', $effect->argument());

$invalidEffectJson = array(
    'typo' => 'notification',
    'attribute' => 'message',
    'value' => 'Software update available.',
);
assert_compile_error(Effect::compile($invalidEffectJson, 'rule.then'), 'rule.then: expected type');

$invalidEffectJson = array(
    'type' => '',
    'attribute' => 'message',
    'value' => 'Software update available.',
);
assert_compile_error(Effect::compile($invalidEffectJson, 'rule.then'), 'rule.then.type: must be a non-empty string');

$invalidEffectJson = array(
    'type' => 'notification',
    'attribute' => 'massage',
    'value' => 'Software update available.',
);
assert_compile_error(Effect::compile($invalidEffectJson, 'rule.then'), 'rule.then.notification: unsupported attribute: massage');

$invalidEffectJson = array(
    'type' => 'notification',
    'attribute' => 'value',
    'value' => 'Software update available.',
);
assert_compile_error(Effect::compile($invalidEffectJson, 'rule.then'), 'rule.then.notification: unsupported attribute: value');

$invalidEffectJson = array(
    'type' => 'health',
    'attribute' => 'value',
    'value' => 42,
);

assert_compile_error(Effect::compile($invalidEffectJson, 'rule.then'), 'rule.then.value: must be a non-empty string');

$invalidEffectJson = array(
    'type' => 'health',
    'attribute' => 'value',
    'value' => '',
);

assert_compile_error(Effect::compile($invalidEffectJson, 'rule.then'), 'rule.then.value: must be a non-empty string');

$invalidEffectJson = array(
    'type' => 'health',
    'attribute' => 'value',
    'value' => 'hungover',
);
assert_compile_error(Effect::compile($invalidEffectJson, 'rule.then'), 'rule.then.health: unsupported value hungover');

$invalidEffectJson = array(
    'type' => 'clickAction',
    'attribute' => 'value',
    'value' => 'clicked',
);

assert_compile_error(Effect::compile($invalidEffectJson, 'rule.then'), 'rule.then: unsupported type clickAction');
