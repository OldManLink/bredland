<?php

declare(strict_types=1);

function assertSame($expected, $actual): void
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

function assertNotSame($unexpected, $actual): void

{
    if ($unexpected === $actual) {
        fwrite(STDERR,
            "Assertion failed\n" .
            "Did not expect: " . var_export($unexpected, true) . "\n"
        );
        exit(1);
    }
}