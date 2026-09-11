# MikroTik device-mode provenance

This document records why the non-default RouterOS device-mode capabilities on the RB4011 are enabled, and reconstructs the events that caused them to be enabled.

It exists because several months after the original configuration work, it became surprisingly difficult to answer a seemingly simple question:

> Why are `fetch`, `scheduler`, and `email` enabled in device-mode, and when did we physically confirm those changes?

The answer was eventually reconstructed in September 2026 from the original ChatGPT conversation export, contemporary RouterOS output, NOC heartbeat history, and router logs.

This is the resulting archaeological record.

---

## Current relevant state

The RB4011 remains in RouterOS `home` device-mode.

The relevant explicitly enabled capabilities are:

```text
scheduler: yes
fetch:     yes
email:     yes
```

These were not all enabled at the same time.

`fetch` was enabled first because the original MikroTik NOC heartbeat needed `/tool fetch`.

`scheduler` was enabled later, when the heartbeat evolved from a manually executed proof of concept into an installed script that RouterOS would run every five minutes.

`email` was enabled at the same time as `scheduler`. It was not required by the heartbeat itself; it was deliberately enabled while making the second physical device-mode change so that another physical confirmation would not be necessary if RouterOS email functionality was wanted later.

The history below explains how we know this.

---

# Background

On 25 June 2026, BRD-001 was bringing the MikroTik RB4011 into the new Bredland/NOC telemetry system.

The initial architecture was deliberately tiny:

```text
MikroTik
    ↓
HTTPS POST
    ↓
Oderland
    ↓
PHP endpoint
    ↓
JSONL heartbeat history
```

The RouterOS side used `/tool fetch` to POST the heartbeat to the public NOC endpoint.

At this point we had not yet encountered RouterOS device-mode as an operational constraint.

That was about to change.

---

# First encounter with device-mode

At approximately 15:43 UTC, the first RouterOS heartbeat was imported:

```routeros
/import file-name=noc-heartbeat.rsc
```

Instead of sending the heartbeat, RouterOS rejected the operation:

```text
Script Error: failure: not allowed by device-mode (/tool/fetch; line 17)
```

Inspection showed that the RB4011 was running in `home` device-mode and that:

```text
fetch: no
```

This was the first reason for changing device-mode.

The required change was:

```routeros
/system/device-mode/update fetch=yes
```

RouterOS device-mode changes deliberately require physical confirmation. A remotely initiated change is not sufficient by itself.

At the time, however, we initially misunderstood what counted as confirmation.

---

# The failed first attempt

After requesting `fetch=yes`, the router was rebooted normally.

That did **not** confirm the device-mode change.

At 15:51 UTC Peter reported:

> I changed the setting and rebooted the router but it didn't take

At 15:52:30 UTC the router confirmed the failure:

```text
[admin@RB4011iGS] > /system/device-mode/print

                 mode: home
     allowed-versions: 7.13+,6.49.8+
              flagged: no
     flagging-enabled: yes
            scheduler: no
                socks: no
                fetch: no
                 pptp: yes
                 l2tp: yes
       bandwidth-test: no
          traffic-gen: no
              sniffer: no
                ipsec: yes
                romon: no
                proxy: no
              hotspot: no
                  smb: yes
                email: no
             zerotier: no
            container: no
  install-any-version: no
           partitions: no
          routerboard: no
        attempt-count: 1
```

The important lines were:

```text
fetch: no
attempt-count: 1
```

The router had registered the requested device-mode change, but it had not received an acceptable physical confirmation.

We then established that an ordinary RouterOS software reboot was not sufficient.

The change needed to be requested again, followed by genuine physical intervention: either the appropriate hardware-button confirmation or a true removal and restoration of power within the confirmation window.

For this installation, we chose the unambiguous method:

```text
remove power
wait
restore power
```

---

# Physical confirmation #1: enabling `fetch`

The command was therefore requested again:

```routeros
/system/device-mode/update fetch=yes
```

The RB4011 was then physically power-cycled.

We do not have a single line saying:

```text
15:55:xx device-mode fetch confirmed
```

but the surviving evidence brackets the event very tightly.

At 15:52:30 UTC:

```text
fetch: no
attempt-count: 1
```

At 15:58:17 UTC:

```text
[admin@RB4011iGS] > /import file-name=noc-heartbeat.rsc

      status: finished
        code: 200
  downloaded: 1KiB
       total: 1KiB
    duration: 1s

Script file loaded and executed successfully
```

That HTTP 200 came from `/tool fetch` running **on the MikroTik itself**.

Therefore `fetch` was unquestionably enabled by then.

The NOC history provides an even nicer timestamp.

One of the first genuine MikroTik heartbeat records was:

```json
{
  "schema": 1,
  "ts": "2026-06-25T15:57:45Z",
  "host": "mikrotik",
  "uptime": "00:02:25"
}
```

Subtracting the reported uptime from the heartbeat timestamp places the boot at approximately:

```text
2026-06-25 15:55:20 UTC
```

Contemporary RouterOS logs later recovered during the archaeology also contained an improper-shutdown/power-loss event around this period:

```text
2026-06-25 15:56:05 system,critical,info cloud change time ...
2026-06-25 15:56:06 system,error,critical router rebooted without proper shutdown, probably power outage
```

The timestamps around boot are affected by the router correcting its clock after startup, so they should not be treated as sub-second forensic timestamps.

Taken together, however, the evidence is conclusive:

> The first successful physical device-mode confirmation occurred at approximately 15:55–15:56 UTC on 25 June 2026 and enabled `fetch`.

After this, the MikroTik could successfully send telemetry to the NOC.

At this point:

```text
fetch:     yes
scheduler: no
email:     no
```

---

# The heartbeat evolves

The first successful `.rsc` file executed the heartbeat immediately when imported.

That proved the complete transport path:

```text
RouterOS
    ↓
/tool fetch
    ↓
HTTPS
    ↓
Oderland
    ↓
PHP endpoint
    ↓
authentication
    ↓
JSONL append
```

But that was only a proof of concept.

For the permanent design, the heartbeat needed to become an installed RouterOS script, with a scheduler invoking it every five minutes.

The installer therefore evolved to create something equivalent to:

```text
/system script
    noc-heartbeat

/system scheduler
    every 5 minutes → noc-heartbeat
```

This exposed the next device-mode restriction.

---

# Discovery of the scheduler restriction

At 16:28:43 UTC, the new installer was imported:

```routeros
/import file-name=install-noc-heartbeat.rsc
```

RouterOS rejected the scheduler creation:

```text
failure: not allowed by device-mode (/system/scheduler/add; line 33)
```

We had discovered:

```text
scheduler: no
```

This meant another device-mode change — and therefore another physical confirmation — would be required.

Peter quite reasonably objected to repeatedly power-cycling a router merely because we were discovering device-mode restrictions one at a time.

At 16:32 UTC he asked to inspect all the device-mode options before doing another reboot, specifically so that we could batch any sensible changes.

The comment at the time was, memorably:

> bad for my uptime stats!

This turned out to be a useful design decision.

---

# Deciding what to enable

We reviewed the available device-mode capabilities rather than blindly enabling everything.

The principle was:

> Remain in `home` mode and enable only capabilities for which we have a plausible operational use.

For the immediate NOC work:

```text
fetch      definitely required
scheduler  definitely required
```

`email` was also considered useful enough to enable while physical confirmation was already going to be necessary.

The proposed combined update was:

```routeros
/system/device-mode/update \
    fetch=yes \
    scheduler=yes \
    email=yes
```

Strictly speaking, `fetch=yes` was redundant at this point because the first physical confirmation had already enabled it.

Including it again was harmless and made the desired final state explicit.

The meaningful new capabilities in this second change were therefore:

```text
scheduler=yes
email=yes
```

Other capabilities were deliberately left alone.

In particular, this was **not** an attempt to weaken device-mode generally or to turn the router into an unrestricted RouterOS installation.

The RB4011 remained in:

```text
mode: home
```

---

# Physical confirmation #2: `scheduler` and `email`

Rather than interrupt work immediately, the second physical confirmation was deliberately deferred until Peter was about to leave for his evening walk.

At 16:38 UTC the plan was explicitly recorded:

> OK, I'll do the next settings change/reboot just before my evening walk, and we can finalise the slice after that.

The intended operation was:

```text
scheduler=yes
email=yes
one final physical power cycle
```

At 17:52:17 UTC Peter returned with the post-change RouterOS state:

```text
[admin@RB4011iGS] > /system/device-mode/print

                 mode: home
     allowed-versions: 7.13+,6.49.8+
              flagged: no
     flagging-enabled: yes
            scheduler: yes
                socks: no
                fetch: yes
                 pptp: yes
                 l2tp: yes
       bandwidth-test: no
          traffic-gen: no
              sniffer: no
                ipsec: yes
                romon: no
                proxy: no
              hotspot: no
                  smb: yes
                email: yes
             zerotier: no
            container: no
  install-any-version: no
           partitions: no
          routerboard: no
        attempt-count: 0
```

The important result was:

```text
scheduler:     yes
fetch:         yes
email:         yes
attempt-count: 0
```

The second physical device-mode confirmation had therefore succeeded.

This is the origin of the three non-default capabilities that later prompted the archaeology.

---

# BRD-001 finally runs automatically

At 17:56:44 UTC, after the second device-mode confirmation, the permanent heartbeat installer succeeded:

```text
[admin@RB4011iGS] > /import file-name=install-noc-heartbeat.rsc
Script file loaded and executed successfully
```

The installed script appeared as:

```text
0   name="noc-heartbeat"
    owner="admin"
    policy=read,write,test
    dont-require-permissions=no
    run-count=0
```

and the scheduler as:

```text
Columns: NAME, START-TIME, INTERVAL, ON-EVENT, RUN-COUNT

# NAME           START-TIME  INTERVAL  ON-EVENT       RUN-COUNT
0 noc-heartbeat  startup     5m        noc-heartbeat          0
```

There was a moment of alarm because RouterOS printed:

```text
Flags: I - INVALID
```

above the script listing.

This was merely the **flag legend**.

The actual row began:

```text
0   name="noc-heartbeat"
```

not:

```text
0 I name="noc-heartbeat"
```

so the heartbeat script itself was valid.

BRD-001 could now run autonomously.

The basic mechanism established that evening remains conceptually recognizable in the much more capable telemetry system that followed:

```text
RouterOS scheduler
        ↓
telemetry heartbeat script
        ↓
HTTPS POST
        ↓
NOC
```

---

# The misleading later uptime reset

One reason the device-mode history became confusing several months later was the NOC heartbeat history.

The complete MikroTik heartbeat archive contains one obvious uptime reset on 25 June:

```text
2026-06-25 22:40:42Z  uptime 04:50:34
2026-06-25 22:52:58Z  uptime 00:05:06
```

For a while during the September archaeology, this looked like it might identify one of the device-mode physical confirmations.

It does not.

The original conversation proves that the late-evening reset belongs to a completely separate event.

At approximately 22:34 UTC, Peter began a RouterOS upgrade.

At approximately 22:50 UTC, the upgrade was complete and the router reported:

```text
RouterOS 7.23.1
uptime: 54s
```

RouterBOARD firmware was also brought to 7.23.1 during that upgrade sequence.

Therefore the heartbeat uptime reset around 22:47–22:48 UTC is associated with the RouterOS/RouterBOARD upgrade, **not** device-mode.

Because the heartbeat interval was five minutes, multiple closely spaced upgrade-related reboots could appear in the telemetry history as a single uptime discontinuity.

Consequently the historical record supports:

> At least one reboot occurred during the RouterOS/RouterBOARD upgrade sequence.

It does **not** prove that exactly one reboot occurred.

This distinction matters because otherwise the heartbeat archive appears to contradict the recollection of two earlier physical device-mode confirmations.

It does not.

Those confirmations happened hours earlier.

---

# Definitive timeline

The reconstructed sequence for 25 June 2026 is therefore:

```text
~15:43 UTC
    First heartbeat import attempted.

    /tool fetch rejected:
    "not allowed by device-mode"

    fetch=no


~15:50 UTC
    First attempt made to enable fetch.

    Router rebooted normally.

    Device-mode change NOT confirmed.


15:52:30 UTC
    Direct observation:

    fetch=no
    scheduler=no
    email=no
    attempt-count=1


15:52–15:58 UTC
    /system/device-mode/update fetch=yes
    requested again.

    Router physically power-cycled.


~15:55–15:56 UTC
    PHYSICAL DEVICE-MODE CONFIRMATION #1.

    fetch becomes enabled.


15:57:45 UTC
    NOC receives MikroTik heartbeat with:

    uptime=00:02:25


15:58:17 UTC
    MikroTik /tool fetch successfully receives HTTP 200.

    fetch=yes is therefore proven operational.


16:28:43 UTC
    Permanent heartbeat installer attempts to add scheduler.

    RouterOS rejects it:

    "not allowed by device-mode (/system/scheduler/add; line 33)"


16:32 UTC
    Decision made to inspect device-mode comprehensively
    and batch useful changes before another physical reboot.

    Desired state:

    fetch=yes
    scheduler=yes
    email=yes


16:38 UTC
    Second change deliberately deferred until evening walk.


~17:51 UTC
    PHYSICAL DEVICE-MODE CONFIRMATION #2.

    Meaningful new capabilities:

    scheduler=yes
    email=yes

    fetch remains yes.


17:52:17 UTC
    Direct post-change observation:

    scheduler=yes
    fetch=yes
    email=yes
    attempt-count=0


17:56:44 UTC
    Permanent heartbeat installer succeeds.

    noc-heartbeat script installed.
    Five-minute scheduler installed.


~22:47–22:50 UTC
    SEPARATE EVENT.

    RouterOS / RouterBOARD 7.23.1 upgrade sequence.

    This explains the late-night uptime reset visible
    in the archived NOC heartbeat history.
```

---

# How many times was the router actually rebooted?

This question needs slightly careful wording.

During the September 2026 archaeology, Peter remembered having physically confirmed device-mode changes approximately twice.

That recollection was correct.

There were **two successful physical device-mode confirmations**:

1. `fetch`
2. `scheduler` + `email`

However, those were not the only router restarts that day.

There was also:

- an earlier reboot that failed to confirm the first `fetch` change because it was the wrong kind of reboot;
- later reboot activity associated with the RouterOS/RouterBOARD 7.23.1 upgrade.

Therefore:

> “The router was rebooted exactly twice on 25 June” is false.

But:

> “We physically power-cycled/confirmed the router twice for device-mode changes” is supported by the reconstructed evidence.

That distinction was the key to resolving the apparent contradiction between Peter's recollection, the conversation, the RouterOS logs, and the heartbeat history.

---

# Why these capabilities are enabled today

## `fetch=yes`

Required for RouterOS scripts to make the outbound HTTPS requests used by the NOC telemetry heartbeat.

This was the first capability enabled.

Without it, RouterOS produced:

```text
failure: not allowed by device-mode (/tool/fetch ...)
```

It remains required.

---

## `scheduler=yes`

Required for the RouterOS scheduler to invoke the telemetry heartbeat periodically.

This was discovered only after the first manual heartbeat path was already working.

Without it, the permanent installer produced:

```text
failure: not allowed by device-mode (/system/scheduler/add ...)
```

It remains required.

---

## `email=yes`

Enabled opportunistically during the second physical device-mode confirmation.

It was **not required by BRD-001**.

The reasoning was simply that another physical confirmation was already necessary for `scheduler`, and email was a sufficiently plausible future RouterOS capability to justify enabling it at the same time.

Therefore Future Peter should not search the telemetry architecture for some hidden dependency on RouterOS email.

There isn't one.

---

# Capabilities that remain disabled

The archaeological investigation also reinforced an important distinction:

> A RouterOS device-mode capability being permitted is not the same thing as exposing a network service.

Device-mode is a permission boundary governing what functionality RouterOS is allowed to use.

For example, enabling a tool such as the packet sniffer in device-mode would permit appropriately authorized RouterOS users/scripts to invoke that functionality. It would not, by itself, expose some new unauthenticated WAN service.

Nevertheless, the policy for this router remains conservative:

> Keep `home` mode and enable capabilities individually when an actual requirement exists.

There is no reason to enable every device-mode option merely to avoid future physical confirmations.

Physical confirmation is inconvenient by design.

That inconvenience is part of the security boundary.

---

# Archaeology performed in September 2026

This history was reconstructed on 11 September 2026.

The investigation used the ChatGPT account export, particularly the conversation:

```text
Bredland (archive #1)
```

from 25 June 2026.

The relevant conversation ID in that export was:

```text
6a3a7a70-1868-83ed-8f59-791599db9f58
```

The export was searched directly with `jq` against:

```text
conversations-000.json
conversations-001.json
```

This was necessary because the original Bredland project conversations had become very large, and some had subsequently been archived.

The investigation initially followed one false lead: the single obvious MikroTik uptime reset in the NOC heartbeat history.

Returning to the original conversation proved that this reset belonged to the RouterOS upgrade, not device-mode.

Narrow timestamp searches then recovered both actual device-mode episodes.

This is a useful lesson for future archaeology:

> Telemetry is evidence, but context determines what the evidence means.

The heartbeat archive accurately told us that the router rebooted.

It could not tell us **why**.

The original conversation supplied that missing context.

---

# Credential footnote

During the September archaeology, an old RouterOS script listing from 25 June was recovered.

That listing contained the original MikroTik telemetry token in plaintext.

Because the token appeared in the historical ChatGPT conversation, its first characters were compared with the current production token on Oderland.

Historical token prefix:

```text
mikrotik.v1.2DqBjtFx
```

Current production token prefix at the time of verification:

```text
mikrotik.v1.DgBm2hcs
```

They differ.

Therefore the historical credential had already been rotated and was no longer the production MikroTik telemetry credential.

The complete tokens are intentionally **not** recorded in this document.

---

# Final conclusion

The current RouterOS device-mode state is not accidental and does not result from a broad relaxation of `home` mode.

It is the product of two deliberate, physically confirmed changes made while building the first MikroTik NOC heartbeat on 25 June 2026.

The provenance is:

```text
Physical confirmation #1
    └── fetch=yes
          └── required for HTTPS telemetry POST


Physical confirmation #2
    ├── scheduler=yes
    │     └── required for five-minute automatic heartbeat
    │
    └── email=yes
          └── enabled opportunistically for possible future use
```

The first attempt at enabling `fetch` failed because an ordinary software reboot was mistakenly used as confirmation.

The later uptime reset visible in NOC history was unrelated and came from the RouterOS/RouterBOARD 7.23.1 upgrade.

Thus the memory that started the September investigation was essentially right:

> We really did physically confirm device-mode twice.

What memory had lost was the interesting part: **why**.

Now Future Peter doesn't have to remember.

He only has to find this file. 😄

