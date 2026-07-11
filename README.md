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

Run the complete validation workflow with:

```bash
./tests/run-all.sh
```
This command builds (or reuses) the canonical PHP 5.5 container, executes all shell and PHP tests, validates every PHP file in the repository using php -l, and verifies that PHP 5.6 language features are rejected.

## Disaster Recovery

A compressed disk image of the configured system is maintained separately from this repository. Changes affecting the installed system are documented under `docs/image/` so the evolution of the image remains reproducible.

The repository contains the information required to understand, configure and reproduce the system, while the disk image provides a convenient recovery point.

## Acknowledgements

Many thanks to **Lars Thomas Bredland** for donating the Raspberry Pi during Oslo Open 2026.

It has found a good home.
