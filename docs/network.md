# Network

## Overview

Bredland is a small infrastructure node on the Hugosson-Miller home network.

It is expected to run lightweight services and scripts that support monitoring, maintenance and experimentation.

## Identity

| Property | Value |
|----------|-------|
| Hostname | `bredland` |
| Local DNS name | `bredland.arcanel.local` |
| Baseline IP address | `192.168.88.5` |

## NOC Heartbeat

Bredland may report status to an external NOC endpoint.

The endpoint URL and authentication token are treated as deployment-specific secrets and must not be committed to this repository.

Runtime secrets are expected to live outside version control, for example:

```text
/etc/bredland/secrets.env
```

## MikroTik Heartbeat

A future MikroTik RouterOS script may independently report router uptime, CPU and memory to the NOC.

That script should be maintained in this repository only as a template. Deployment-specific endpoint and token values must be injected at deployment time and stored on the router itself.

The router heartbeat must not depend on Bredland, NAS availability or any other internal host, because its purpose is to verify that the router itself can reach the NOC.

## Notes

Historical baseline information is stored under:

```text
docs/history/
```