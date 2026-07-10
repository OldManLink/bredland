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
