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
    'Software update available'
);

$rule = new Rule($predicate, $effect);

assertSame($predicate, $rule->predicate());
assertSame($effect, $rule->effect());

assertSame('update_available', $predicate->receiver());
assertSame('equals', $predicate->comparator());
assertSame(true, $predicate->argument());

assertSame('notification', $effect->type());
assertSame('Software update available', $effect->argument());