# 2026-06-30 — Heartbeat timer persistence disabled

## Summary

The Bredland heartbeat systemd timer is installed with:

```ini
Persistent=no
```

in:

```
templates/bredland/bredland-heartbeat.timer.template
```

## Reason

The heartbeat runs every five minutes and does not require catch-up execution after downtime.

When the timer was configured with `Persistent=yes`, systemd updated the persistent timer stamp file after every execution:

```
/var/lib/systemd/timers/stamp-bredland-heartbeat.timer
```

This resulted in an unnecessary write to the SD card every five minutes.

Setting `Persistent=no` prevents these periodic writes while preserving the desired heartbeat behaviour.

## Verification

After redeploying the timer, the configuration was verified with:

```bash
systemctl show bredland-heartbeat.timer | grep Persistent
```

which reported:

```
Persistent=no
```

A subsequent SD card write audit showed that:

- the heartbeat continued to execute every five minutes;
- `/var/lib/systemd/timers/stamp-bredland-heartbeat.timer` was no longer updated after each execution;
- the remaining persistent writes were limited to expected operating system activity (DHCP lease updates, time synchronisation, package management timers, etc.).

## Rationale

One of the design goals of the Bredland image is to minimise unnecessary writes to the SD card.

Timers should therefore only use `Persistent=yes` when catch-up execution after downtime is actually required. The heartbeat service is intentionally stateless, so missed executions while the device is powered off have no value and do not justify persistent timer state.
