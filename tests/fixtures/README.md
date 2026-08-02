# Production Fixtures

This directory contains a timestamped snapshot of real production data and
dashboard artifacts used by the test suite.

The fixtures are captured directly from production rather than being
hand-written, minimised, sanitised or otherwise adjusted. They are intended to
remain a source of truth for the behaviour and data formats currently produced
by the running system.

The fixture set includes:

```text
fixtures/
├── heartbeats/
│   ├── bredland.json
│   └── mikrotik.json
├── production/
│   ├── index.html
│   └── static/
│       ├── dashboard.js
│       └── style.css
└── last-fetched.timestamp
```

The files form one coherent snapshot:

- `heartbeats/` contains the latest production heartbeat for each client.
- `production/index.html` contains the dashboard rendered from those production
  heartbeats.
- `production/static/` contains the deployed CSS and JavaScript.
- `last-fetched.timestamp` records when the snapshot was captured and provides
  the deterministic clock used when reproducing the rendered dashboard.

## Refreshing the fixtures

Refresh the complete fixture set with:

```bash
./scripts/update-fixtures.sh
```

The script checks whether the existing fixtures remain sufficiently fresh and
skips the network fetch when they can safely be reused.

To fetch a new snapshot regardless of freshness:

```bash
./scripts/update-fixtures.sh --force
```

The refresh is performed as one operation. The timestamp is written last so it
acts as the commit marker for a complete fixture generation.

Do not manually edit or sanitise fixture values. If a real production value
causes a test failure, first determine whether the test or production code has
made an incorrect assumption. The fixtures should continue to represent what
production actually produced.

## When to refresh

A fixture refresh will normally be needed when deliberately changing something
represented by the snapshot, including:

- a heartbeat JSON schema;
- telemetry fields, types or client reporting behaviour;
- client descriptions that affect dashboard rendering;
- HTML structure or rendered content;
- CSS;
- JavaScript;
- formatting or health behaviour that changes the rendered dashboard;
- fixture generation or rendering logic itself;
- production data needed to reproduce a newly discovered edge case.

Refresh the fixtures after the corresponding production change has been
deployed and verified, so the new snapshot becomes the baseline for subsequent
development.

## When not to refresh

Normal day-to-day development should not require continuously refreshing the
fixtures.

The committed snapshot is deliberately stable and deterministic. Tests use the
saved heartbeat data together with `last-fetched.timestamp`, so the same inputs
produce the same output locally and in GitHub Actions without requiring network
access, SSH credentials or production secrets.

Do not refresh fixtures merely to make an unexpected test failure disappear.
An unexplained difference may be revealing a real regression, a brittle
assumption or a production behaviour that the code needs to handle.
