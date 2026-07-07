<?php

function assertSame($expected, $actual)
{
    if ($expected !== $actual) {
        fwrite(STDERR,
            "Assertion failed\n" .
            "Expected: " . var_export($expected, true) . "\n" .
            "Actual:   " . var_export($actual, true) . "\n"
        );
        exit(1);
    }
}

function assertNotSame($unexpected, $actual)
{
    if ($unexpected === $actual) {
        fwrite(STDERR,
            "Assertion failed\n" .
            "Did not expect: " . var_export($unexpected, true) . "\n"
        );
        exit(1);
    }
}

function fail($message)
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}