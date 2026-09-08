# BRD-032 first live RouterOS update

First successful production execution of the trusted
`install-routeros-update` action on 2026-09-08.

The action installed RouterOS 7.24.2.

RPI was armed immediately before the RouterOS command was sent and detected
loss of HTTPS on `192.168.88.1:443`:

- elapsed from RPI start to first failed probe: 15.530383539 s
- last-success-to-first-failure window: 10.602686 ms
- stop reason: `probe-failure`

The RouterOS log is preserved separately. Early post-reboot RouterOS log
timestamps were not yet synchronized; the later cloud time correction must be
taken into account when comparing them with RPI's UTC wall-clock timestamps.