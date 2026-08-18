# Bredland

*A small Raspberry Pi with an unexpectedly interesting story.*

## Introduction

This repository contains the configuration, templates, deployment tooling, tests and documentation for **Bredland**, a Raspberry Pi 4, and the small home-network monitoring system that has grown around it.

The machine was generously donated to me by fellow speedcuber **Lars Thomas Bredland** during **Oslo Open 2026**, and has since become a permanent member of my home lab.

The goal of this repository is to ensure that everything running on Bredland is reproducible, documented and easy to recover. The Raspberry Pi itself is replaceable hardware; the real value lies in its configuration, scripts and accumulated knowledge.

## Design Principles

This project follows a few simple principles:

* Keep things simple.
* Prefer reliability over cleverness.
* Automate repetitive tasks.
* Document decisions.
* Design for straightforward recovery.
* Treat hardware as replaceable.
* Test before deployment.
* Make important behaviour reproducible.

A guiding principle of this repository is that it should be possible to make it public at any time without auditing or rewriting its history for secrets.

Accordingly, this repository contains no **secrets, credentials, deployment endpoints or deployment-specific configuration**. Those are injected at deployment time from sources outside version control. Every repository in the project is intended to be publishable at any time without rewriting history.

### Path conventions

Deployment paths are assumed to be shell-safe.

Specifically:

* Paths must not contain spaces.
* Paths must not contain shell metacharacters.
* Scripts may quote paths but are not expected to escape arbitrary shell input.

This is a deliberate project convention that keeps deployment scripts small, readable and easy to audit.

## Repository Structure

```text
config/       Example configuration templates.
docs/         Project documentation and architecture notes.
scripts/      Development and deployment tools.
templates/    Deployable templates for Bredland, the NOC and MikroTik.
tests/        Automated test suites and reproducible PHP 5.5 environment.

bin/          Reserved for future on-device utilities.
systemd/      Reserved for future checked-in unit files.
```

Additional directories will be added as Bredland's responsibilities evolve.

## Testing

The project uses automated shell and PHP tests together with a reproducible PHP 5.5 Docker environment matching production.

The canonical test entry point is:

```bash
./tests/run-all.sh
```

This builds (or reuses) the PHP 5.5 test container, runs all shell and PHP test suites, validates every PHP file in the repository with `php -l`, and verifies that PHP 5.6 language features are rejected.

### Selecting test suites

Each test suite has a canonical ID.

Examples:

```bash
./tests/run-all.sh php:predicate
./tests/run-all.sh sh:deployment-helpers
```

Language aliases run all suites for that language:

```bash
./tests/run-all.sh php
./tests/run-all.sh shell
```

Selectors may also use shell-style globs:

```bash
./tests/run-all.sh 'php:compiler/*'
./tests/run-all.sh 'php:card*'
```

Quote glob selectors so that they are interpreted by the test runner rather than expanded by the calling shell.

Available suites can be listed with:

```bash
./tests/run-all.sh --list
./tests/run-all.sh --list php
./tests/run-all.sh --list 'php:compiler/*'
```

### Rerunning failures

Failed suite IDs are recorded under:

```text
build/test-results/failed-suites
```

To rerun only the suites that failed in the previous run:

```bash
./tests/run-all.sh --failed
```

### Output control

Use `--failures-only` to suppress successful suite output while retaining summaries and failure details:

```bash
./tests/run-all.sh --failures-only
```

The existing quiet modes are also supported:

```bash
./tests/run-all.sh -q
./tests/run-all.sh -qq
```

Options and selectors may be combined, for example:

```bash
./tests/run-all.sh --failures-only 'php:compiler/*'
./tests/run-all.sh -q php:predicate
./tests/run-all.sh --failed --failures-only
```

### Test results

Each run writes ephemeral result information under:

```text
build/test-results/
```

Per-suite statistics are written under:

```text
build/test-results/statistics/
├── php/
└── sh/
```

PHP suites using `TestSuiteRunner` report individual test counts, allowing the PHP runner to display both suite-level and test-level summaries:

```text
Suite summary: 53 test suites run, 0 skipped, 53 passed, 0 failed, 0 crashed
Test summary: 283 tests run, 0 skipped, 283 passed, 0 failed
```

Shell suites currently report suite-level results only. Some older procedural PHP suites also do not yet contribute individual test counts.

### Intentionally skipping a PHP test

Tests using `TestSuiteRunner` can be temporarily skipped without removing or commenting out the test body:

```php
$runner->skip(
    'renders temporary legacy case',
    'Waiting for the new implementation',
    function () {
        // Test body remains intact.
    }
);
```

A skipped test is reported explicitly, counted as skipped, and does not prevent later tests in the same suite from running. Changing `skip` back to `test` restores the test normally.

## Disaster Recovery

A compressed disk image of the configured system is maintained separately from this repository. Changes affecting the installed system are documented under `docs/image/` so the evolution of the image remains reproducible.

The repository contains the information required to understand, configure and reproduce the system, while the disk image provides a convenient recovery point.

## Acknowledgements

Many thanks to **Lars Thomas Bredland** for donating the Raspberry Pi during Oslo Open 2026.

It has found a good home.
