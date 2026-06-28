# Bredland Hardware

This document describes the physical hardware used by Bredland and records commissioning measurements that may be useful when maintaining or rebuilding the system.

## Platform

* Raspberry Pi 4 Model B (4 GB)
* Aluminium enclosure with passive cooling columns, model `OCW-0004-Black`
* Samsung 64 GB microSD card
* Official USB-C power supply (installed 2026-06-28)

## Thermal commissioning

Measurements were taken with the system idle after commissioning, in the form of an A/B/A test.

| Configuration                     |   Uptime | Temperature |
| --------------------------------- | -------: | ----------: |
| Bare board                        | 4 h 34 m |      45.2°C |
| Enclosure + Synology airflow (A₁) | 5 h 24 m |      35.5°C |
| Enclosure, still air (B)          | 6 h 46 m |      37.4°C |
| Enclosure + Synology airflow (A₂) | 8 h 12 m |      35.5°C |


### Conclusions

The Aluminium enclosure reduced the idle CPU temperature by approximately 10°C compared with the bare Raspberry Pi.

Positioning the enclosure adjacent to the Synology NAS so that the NAS exhaust airflow passes over the enclosure fins reduced the idle temperature by a further approximately 2°C.

The production installation therefore places Bredland beside the Synology NAS with the enclosure fins aligned with the airflow.

## Power

The official USB-C power supply was verified during commissioning.

Observed after more than five hours of uptime:

* No undervoltage events.
* `vcgencmd get_throttled` reported `0x0`.
* Stable operation.

## Notes

The commissioning measurements provide a baseline for future maintenance. Significant deviations in operating temperature should be investigated.
