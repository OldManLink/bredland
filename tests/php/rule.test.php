#!/usr/bin/env php
<?php

require_once getenv('TEST_CONFIG');
require_once __DIR__ . '/lib/testlib.php';

$nocRoot = dirname(dirname(__DIR__)) . '/templates/noc';

require_once $nocRoot . '/lib/rule.php';

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