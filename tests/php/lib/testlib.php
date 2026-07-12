<?php

function assertSame($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        fwrite(STDERR,
            "Assertion failed" . ($message === '' ? '' : ": " . $message) . "\n" .
            "Expected: " . var_export($expected, true) . "\n" .
            "Actual:   " . var_export($actual, true) . "\n"
        );
        exit(1);
    }
}

function assertTrue($actual, $message = '') {
    assertSame(true, $actual, $message);
}

function assertFalse($actual, $message = '') {
    assertSame(false, $actual, $message);
}

function fail($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function assertIdentifier($identifier, $message = '') {
    assertTrue(
        preg_match('/^[a-z][a-z0-9_]*$/', $identifier) === 1,
        $message === ''
            ? "Expected valid identifier: $identifier"
            : $message
    );
}

function required_string($array, $key, $context) {
    assertTrue(isset($array[$key]), "$context must define $key");
    assertTrue(is_string($array[$key]) && $array[$key] !== '', "$context $key must be a non-empty string");

    return $array[$key];
}

function assert_allowed_keys($required, $allowed, $actual, $context)
{
    $actualKeys = array_keys($actual);

    foreach ($required as $key) {
        assertTrue(
            array_key_exists($key, $actual),
            "$context is missing required property: $key"
        );
    }

    foreach ($actualKeys as $key) {
        assertTrue(
            in_array($key, $allowed, true),
            "$context contains unknown property: $key"
        );
    }
}

function assert_compile_error($result, $message) {
    assertSame(0, count($result->rules()));
    assertSame(1, count($result->messages()));
    assertSame($message, $result->messages()[0]);
}
