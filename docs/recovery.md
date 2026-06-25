# Disaster Recovery

## Philosophy

Bredland is designed to be reproducible.

The Raspberry Pi itself is considered replaceable hardware. The important assets are:

* this repository;
* deployment-specific secrets stored outside version control;
* periodic compressed disk images.

## Recovery Procedure

In the event of an SD card failure:

1. Write the latest archived disk image to a replacement microSD card.
2. Install the card in the Raspberry Pi.
3. Boot the system.
4. Verify normal operation.
5. If required, restore any deployment-specific secrets that are intentionally excluded from this repository.

## Repository vs Disk Image

This repository contains:

* scripts;
* documentation;
* templates;
* system configuration;
* deployment tools.

The disk image contains:

* the complete operating system;
* installed packages;
* user accounts;
* deployed configuration;
* runtime state.

The two complement one another.

## Future Work

Future versions may support complete reconstruction of Bredland directly from this repository, reducing reliance on archived disk images.
