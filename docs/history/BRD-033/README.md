# BRD-033 first live RouterBOOT update

First successful production execution of the trusted
`install-routerboot-update` action on 2026-09-15.

The action upgraded RouterBOOT firmware to match the installed RouterOS
version and rebooted the MikroTik.

RPI was armed immediately before the RouterOS command was sent and detected
loss of HTTPS on `192.168.88.1:443`:

- elapsed from RPI start to first failed probe: 0.788209266 s
- last-success-to-first-failure window: 10.529059 ms
- stop reason: `probe-failure`

Temporary trusted-service timing instrumentation measured the synchronous
action path:

- validator: 385.758475 ms
- pre-action hook: 1.327016 ms
- RouterOS action executor: 757.570370 ms
- HTTP response: 0.434827 ms
- total validator-to-response time: 1.145148910 s

The green success toast on the iPhone was subjectively observed about
3–4 seconds after confirming the action. The measured server-side path was
only about 1.15 seconds, so the remaining perceived delay occurred outside
the instrumented validator/hook/executor/response interval. This should be
measured again during the next live RouterOS package update.

RPI detected RouterOS HTTPS disappearing roughly 30 ms after the trusted
service had completed and flushed its successful HTTP response.

Bredland subsequently observed its Ethernet carrier go down and later return.
The LAN connection recovered automatically and Bredland reacquired
`192.168.88.5`.

The MikroTik, Bredland, trusted-service, and RPI logs are preserved separately
in this directory.

Early post-reboot MikroTik log timestamps were in UTC. RouterOS later corrected
its clock to CEST, producing an apparent two-hour jump. Add two hours to the
pre-correction MikroTik timestamps when comparing them with Bredland's CEST
journal timestamps. RPI wall-clock timestamps are UTC.

For comparison, the earlier BRD-032 live RouterOS package update took
15.530383539 s from RPI start to first failed HTTPS probe. The RouterBOOT
reboot therefore caused HTTPS to disappear much sooner than the full RouterOS
package update.