# Bredland Image Documentation

This directory documents the evolution of the Bredland system image.

## Philosophy

The **golden image** is an immutable snapshot of a known-good system.

The **restore image** is derived from the golden image and is intended for restoring Bredland onto replacement media.

The running system may continue to evolve after a restore image has been created. Those changes are documented individually and become part of the next published restore image.

The goal is that every published restore image is reproducible from the golden image by applying the documented changes.

## Directory structure

### `changes/`

One document per change made to the running system.

Each document records:

* motivation;
* implementation;
* verification;
* rollback procedure;
* notes.

### `releases/`

One document per published restore image.

Each release records:

* when the restore image was created;
* how it was validated;
* which documented changes are included.

### `evolution.md`

A chronological index of image changes and restore image releases.

## Workflow

1. Make a change to the running system.
2. Document the change in `changes/`.
3. Commit the documentation to Git.
4. When appropriate, create and validate a new restore image.
5. Add a new release document describing that restore image.

The golden image is never modified.
