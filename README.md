# Bredland

*A small Raspberry Pi with an unexpectedly interesting story.*

## Introduction

This repository contains the configuration, scripts and documentation for **Bredland**, a Raspberry Pi 4 that quietly performs various infrastructure tasks on my home network.

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

A guiding principle of this repository is that it should be possible to make it public at any time without auditing or rewriting its history for secrets.

Accordingly, this repository contains **no secrets, credentials, deployment endpoints or deployment-specific configuration**. Those are injected at deployment time from sources outside version control.

## Repository Structure

```text
bin/         Scripts intended to run on Bredland.
config/      Example configuration files and templates.
docs/        Documentation.
scripts/     Development and deployment tools run from another machine.
systemd/     Service, timer and mount unit files.
```

Additional directories will be added as Bredland's responsibilities evolve.

## Disaster Recovery

A compressed disk image of the configured system is maintained separately from this repository.

The repository contains the information required to understand, configure and reproduce the system, while the disk image provides a convenient recovery point.

## Acknowledgements

Many thanks to **Lars Thomas Bredland** for donating the Raspberry Pi during Oslo Open 2026.

It has found a good home.
