# Heartbeat Fixtures

This directory contains canonical examples of client heartbeat objects.

Each fixture is copied directly from a real production heartbeat rather than being hand-written or minimised. The fixtures should remain complete examples of the telemetry produced by each client.

Keeping the fixtures representative of real heartbeats avoids introducing discrepancies between production data and the test suite, and provides a reliable reference for future tests.

When a client's heartbeat format changes, replace the corresponding fixture with a fresh heartbeat captured from production.