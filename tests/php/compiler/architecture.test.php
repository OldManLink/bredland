#!/usr/bin/env php
<?php
require_once getenv('TEST_CONFIG');
$phpTestRoot = dirname(__DIR__);
require_once $phpTestRoot . '/lib/testlib.php';
$compilerRoot = dirname(dirname($phpTestRoot)) . '/templates/noc/lib/compiler';

foreach (glob($compilerRoot . '/*.php') as $file) {
    require_once $file;
}

/**
 * Architecture inspection helper.
 *
 * Run:
 *
 *     php scripts/compiler-hierarchy.php
 *
 * to print the current compiler interface/class relationships. This is useful
 * when adding or changing compiler types: inspect the resulting hierarchy here
 * first, then update these architecture tests only when the intended contracts
 * or architectural boundaries have genuinely changed.
 */
$runner = new TestRunner('architecture');

/**
 * Positive architecture contracts.
 *
 * RuntimeVal represents compiled expressions that resolve to values in the
 * context of a heartbeat. This includes both simple values and composed
 * expressions.
 *
 * Predicate deliberately belongs here even though it evaluates a condition:
 * its observable result is still a value -- a boolean. Like SlotVal, it
 * derives that value by composing other runtime values rather than producing
 * side effects. This also allows a Rule to conceptually treat a Predicate and
 * a literal BoolVal as interchangeable boolean conditions.
 *
 * Renderable is deliberately narrower: it represents compiler objects such
 * as MethodVal and OpVal whose runtime representation requires no heartbeat
 * or other context.
 *
 * SlotPart extends RuntimeVal because every part of an interpolated slot must
 * be renderable in heartbeat context.
 */
$runner->test('runtime values implement RuntimeVal', function () {
    $classes = array(
        BoolVal::class,
        Field::class,
        FieldVal::class,
        FloatVal::class,
        HealthVal::class,
        IntVal::class,
        Predicate::class,
        SlotVal::class,
        StrVal::class
    );

    foreach ($classes as $class) {
        assertTrue(is_subclass_of($class, RuntimeVal::class));
    }
});

$runner->test('context-free values implement Renderable', function () {
    assertTrue(is_subclass_of(FormatVal::class, Renderable::class));
    assertTrue(is_subclass_of(MethodVal::class, Renderable::class));
    assertTrue(is_subclass_of(OpVal::class, Renderable::class));
    assertTrue(is_subclass_of(TypeVal::class, Renderable::class));
});

$runner->test('slot parts are runtime values', function () {
    assertTrue(is_subclass_of(SlotPart::class, RuntimeVal::class));
});

/**
 * Negative architecture contracts.
 *
 * These assertions protect deliberate boundaries rather than merely recording
 * the current class hierarchy.
 *
 * Side-effecting compiler objects are deliberately not RuntimeVal.
 *
 * Action and Rule participate in rendering by applying behaviour and may
 * produce side effects. They do not resolve to values that can be substituted
 * into other runtime expressions.
 *
 * Predicate is intentionally excluded from this negative contract: although
 * it evaluates behaviour-like logic, it ultimately resolves to a boolean and
 * therefore is a RuntimeVal.
 *
 * Change these assertions only if those architectural meanings themselves
 * change, not simply to make a new inheritance relationship convenient.
 */
$runner->test('behavioural compiler objects are not runtime values', function () {
    assertFalse(is_subclass_of(Action::class, RuntimeVal::class));
    assertFalse(is_subclass_of(Rule::class, RuntimeVal::class));
});

$runner->test('ReceiverVal and FieldList are not value renderers', function () {
    assertFalse(is_subclass_of(ReceiverVal::class, RuntimeVal::class));
    assertFalse(is_subclass_of(ReceiverVal::class, Renderable::class));
    assertFalse(is_subclass_of(FieldList::class, RuntimeVal::class));
    assertFalse(is_subclass_of(FieldList::class, Renderable::class));
});

$runner->finish();