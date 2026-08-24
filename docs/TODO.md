# BRD-030 TODO — Trusted-network dashboard mode

## Proven by feasibility spike

- [x] Public HTTPS page can probe Bredland over the trusted network.
- [x] Probe works over WireGuard on Mac Chrome.
- [x] Probe works over WireGuard on iPhone Safari.
- [x] Untrusted/WG-off path can fail cleanly after a bounded timeout.
- [x] `AbortController` cancels the failed probe rather than leaving it pending.
- [x] Successful discovery can dynamically load opaque JavaScript from Bredland.
- [x] Successful discovery can dynamically load opaque CSS from Bredland.
- [x] Opaque extensionless assets work when Bredland sends explicit MIME types.
- [x] No trusted assets are requested when discovery fails.
- [x] HTTPS is required for the production browser path.
- [x] Port `8443` is unsuitable for the supported iPhone/WireGuard path; `8081` worked in the spike.
- [x] Browser TLS trust must already be established for silent discovery.

Do not rebuild or rediscover these fundamentals.

---

## 1. Choose the production HTTPS identity

Before production code:

- [ ] Choose the permanent trusted Bredland origin.
- [ ] Choose the production port; do not assume `8443`.
- [ ] Choose the certificate strategy.
  - Prefer normal browser trust with no per-browser warning/exception.
  - Evaluate an opaque FQDN with a publicly trusted certificate, e.g. DNS-01 issuance, before committing to a private CA.
  - If a private CA is used, document installation and trust steps for Mac and iPhone.
- [ ] Ensure certificate SAN matches the actual hostname/IP used by browsers.
- [ ] Define certificate renewal/replacement procedure.
- [ ] Keep private keys and concrete deployment values outside Git.
- [ ] Put deployment-private values in `/etc/bredland/secrets.env`.

Production must not require manually visiting the probe URL and accepting a certificate warning.

---

## 2. Build the real Bredland HTTPS service

Replace the throwaway Python server with boring permanent infrastructure.

- [ ] Decide the smallest appropriate server/service.
- [ ] Serve HTTPS only.
- [ ] Expose the service only through the trusted LAN/WireGuard path; no WAN publication/NAT.
- [ ] Add a minimal discovery endpoint.
- [ ] Allow the NOC origin explicitly where CORS requires it.
- [ ] Serve opaque JavaScript as `application/javascript`.
- [ ] Serve opaque CSS as `text/css`.
- [ ] Do not expose directory listings.
- [ ] Do not expose source maps, source filenames, development paths or comments unnecessarily.
- [ ] Run as a restricted service user where practical.
- [ ] Protect certificate/private configuration permissions.
- [ ] Add deterministic start/stop/restart/status behaviour.
- [ ] Add deployment/rehearsal tooling and explicit verification.

Keep this service deliberately ignorant of BRD-031 actions.

---

## 3. Design the discovery response and opaque assets

The public NOC must know as little as possible.

Preferred flow:

```text
public bootstrap
    ↓
probe opaque Bredland endpoint
    ↓ success
response supplies opaque asset locations
    ↓
inject trusted <script> + <link>
```

- [ ] Decide the smallest successful probe response contract.
- [ ] Do not embed trusted JS/CSS implementation in public assets.
- [ ] Prefer not to embed trusted JS/CSS resource names in public assets either.
- [ ] Let successful discovery reveal the opaque asset locations when practical.
- [ ] Generate opaque/fingerprinted trusted resource names.
- [ ] Keep their concrete deployed values in `/etc/bredland/secrets.env`.
- [ ] Ensure knowledge of any resource URL grants no authority.
- [ ] No source maps in production.

Trusted resources for BRD-030 contain only enough implementation for the presentation state.

---

## 4. Integrate discovery into the public NOC cleanly

First inspect the **current** NOC composition/rendering code before choosing classes.

Do not make trusted discovery a `Client`, rule, notification or other domain concern.

- [ ] Characterise current NOC rendering/bootstrap behaviour with tests.
- [ ] Identify the existing composition/rendering seam where the minimal public bootstrap belongs.
- [ ] Keep trusted-network knowledge out of compiled `Client` domain objects.
- [ ] Add only the minimum public JavaScript required to:
  1. start one probe;
  2. bound it with a timeout;
  3. silently treat failure as ordinary public mode;
  4. consume the successful discovery response;
  5. inject trusted assets into `<head>`.
- [ ] Do not introduce a new OO abstraction merely to objectify a tiny bootstrap helper.
- [ ] If the current OO renderer naturally requires an object boundary, introduce the smallest explicit one and drive it from tests.
- [ ] Inspect current CSP/security headers before implementation.
- [ ] Ensure any required CSP changes do not unnecessarily disclose trusted resource details.
- [ ] Trusted-mode discovery runs asynchronously and must not delay or block ordinary dashboard rendering or interaction.

Failure is normal behaviour and must produce no user-visible warning, degraded state or retry loop.

---

## 5. TDD the public discovery path

Work in tiny red → green steps.

- [ ] RED: existing public rendering is characterised and unchanged.
- [ ] RED: failed discovery loads no trusted assets.
- [ ] GREEN: introduce minimal single probe.
- [ ] RED: unreachable probe does not remain pending indefinitely.
- [ ] GREEN: add bounded `AbortController` timeout.
- [ ] RED: failed discovery produces no application-visible error.
- [ ] RED: failed discovery produces no retry.
- [ ] RED: successful discovery causes exactly the trusted resources returned by Bredland to be injected.
- [ ] RED: trusted resources are never injected before successful discovery.
- [ ] RED: ordinary public assets contain no trusted-mode implementation, selectors or trusted-only strings.

Use deterministic tests wherever browser behaviour does not itself need manual verification.

---

## 6. TDD the Bredland service

- [ ] RED: discovery endpoint returns the agreed minimal success contract.
- [ ] RED: wrong/unexpected paths do not disclose useful information.
- [ ] RED: CORS permits only the intended public NOC origin.
- [ ] RED: opaque JS resource receives the correct MIME type.
- [ ] RED: opaque CSS resource receives the correct MIME type.
- [ ] RED: no source maps/development metadata are served.
- [ ] RED: deployed resource names come from private deployment configuration rather than committed secrets.
- [ ] Add service/deployment tests for configuration, startup and expected resources.

Do not add action APIs in this slice.

---

## 7. Implement trusted presentation

Only after discovery and asset delivery are green:

- [ ] Add trusted CSS on Bredland.
- [ ] Implement the thin viewport border.
- [ ] Choose a subtle grey distinct enough for the operator to recognise.
- [ ] Verify it does not alter dashboard layout.
- [ ] Keep all border selectors/rules out of public CSS.
- [ ] Trusted JavaScript should contain no action implementation in BRD-030.

The finished trusted mode should do essentially one visible thing:

```text
successful trusted discovery
→ subtle viewport border
```

---

## 8. Device/browser provisioning

### Mac / Chrome

- [ ] Verify trusted HTTPS identity requires no manual certificate exception.
- [ ] Grant Chrome Local Network access to `noc.arcanel.se` if prompted.
- [ ] Verify LAN path.
- [ ] Verify hotspot + WireGuard path.
- [ ] Verify hotspot without WireGuard remains ordinary public mode.

### iPhone / Safari

- [ ] Install CA/root profile only if the chosen certificate strategy requires it.
- [ ] Enable full trust only if using a private CA.
- [ ] Verify cellular + WireGuard path.
- [ ] Verify cellular without WireGuard remains ordinary public mode.
- [ ] Verify trusted JS and CSS both load in the trusted case.

If a private test/production CA is no longer required, remove it from the device.

---

## 9. Integration verification

- [ ] Exercise the existing local NOC integration harness.
- [ ] Add BRD-030 integration coverage without weakening existing tests.
- [ ] Verify ordinary public NOC with Bredland unreachable.
- [ ] Verify one probe only.
- [ ] Verify bounded cancellation.
- [ ] Verify no trusted JS/CSS request on failure.
- [ ] Verify trusted JS/CSS request after success.
- [ ] Inspect public HTML/JS/CSS for trusted implementation leakage.
- [ ] Inspect network traffic in both modes.
- [ ] Verify opaque production names.
- [ ] Verify production MIME types.
- [ ] Verify no source maps.
- [ ] Verify LAN.
- [ ] Verify Mac WireGuard.
- [ ] Verify iPhone WireGuard.
- [ ] Verify WG-off/public behaviour.
- [ ] Restart browsers after deliberate mixed-content/TLS experiments before diagnosing leftover security UI state.
- [ ] Verify that ordinary dashboard rendering and interaction are available immediately while the trusted-mode probe is still pending.

---

## 10. Deployment

- [ ] Extend deployment tooling rather than relying on hand-installed spike files.
- [ ] Add required Bredland service/config deployment.
- [ ] Keep concrete URLs, resource names, certificate/private values out of Git.
- [ ] Rehearse deployment first.
- [ ] Run complete PHP and shell suites.
- [ ] Run PHP 5.5.38 and compatibility checks.
- [ ] Run repository-wide `php -l`.
- [ ] Run local NOC integration test.
- [ ] Run dashboard comparison/preview.
- [ ] Deploy using the normal heartbeat safety window.
- [ ] Smoke-test ordinary public mode.
- [ ] Smoke-test trusted LAN mode.
- [ ] Smoke-test trusted WireGuard mode from Mac.
- [ ] Smoke-test trusted WireGuard mode from iPhone.

---

## 11. Final security check

Before marking BRD-030 complete:

- [ ] Public NOC remains observational.
- [ ] Public browser cannot obtain trusted implementation assets without successful Bredland reachability.
- [ ] Trusted presentation can be spoofed locally without gaining any authority.
- [ ] No privileged action endpoint or execution mechanism exists.
- [ ] Nothing in BRD-030 is treated as BRD-031 authorization.

```text
trusted mode visible ≠ privileged action authorized
```

---

## Out of scope

Do **not** drift into:

- RouterOS update execution;
- privileged buttons;
- action APIs;
- capability tokens;
- command queues;
- action authorization;
- BRD-031 architecture.

First make the grey border boring, deterministic and trustworthy.