#!/usr/bin/env php
<?php

if ($argc !== 2) {
    fwrite(STDERR, "Usage: summarize-statistics.php <statistics-directory>\n");
    exit(1);
}

$directory = $argv[1];

if (!is_dir($directory)) {
    fwrite(STDERR, "Statistics directory does not exist: $directory\n");
    exit(1);
}

$run = 0;
$skipped = 0;
$passed = 0;
$failed = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $directory,
        FilesystemIterator::SKIP_DOTS
    )
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'json') {
        continue;
    }

    $statistics = json_decode(
        file_get_contents($file->getPathname()),
        true
    );

    if (!isset($statistics['tests'])) {
        continue;
    }

    $run += $statistics['tests']['run'];
    $skipped += $statistics['tests']['skipped'];
    $passed += $statistics['tests']['passed'];
    $failed += $statistics['tests']['failed'];
}

echo
    "Test summary: $run tests run, " .
    "$skipped skipped, " .
    "$passed passed, " .
    "$failed failed\n";
