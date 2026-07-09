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