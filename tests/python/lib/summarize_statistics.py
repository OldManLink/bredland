#!/usr/bin/env python3

import json
import os
import sys


if len(sys.argv) != 2:
    sys.stderr.write(
        'Usage: summarize_statistics.py <statistics-directory>\n'
    )
    sys.exit(1)

directory = sys.argv[1]

if not os.path.isdir(directory):
    sys.stderr.write(
        'Statistics directory does not exist: {}\n'.format(
            directory,
        )
    )
    sys.exit(1)

run = 0
skipped = 0
passed = 0
failed = 0

for root, dirs, files in os.walk(directory):
    for filename in files:
        if not filename.endswith('.json'):
            continue

        path = os.path.join(
            root,
            filename,
        )

        with open(path, 'r') as file:
            statistics = json.load(file)

        if 'tests' not in statistics:
            continue

        run += statistics['tests']['run']
        skipped += statistics['tests'].get(
            'skipped',
            0,
        )
        passed += statistics['tests']['passed']
        failed += statistics['tests']['failed']

sys.stdout.write(
    'Test summary: {} tests run, {} skipped, {} passed, {} failed\n'.format(
        run,
        skipped,
        passed,
        failed,
    )
)
