# BRD-032 TODO — First trusted dashboard action

Goal: prove one privileged trusted-dashboard action end to end:

> Install an available MikroTik RouterOS update from the existing NOC notification.

The public NOC remains observational. It may expose inert semantic metadata, but all trusted UI, action interpretation, authorization, and execution remain on Bredland / the trusted management path.

## ✅ Public NOC semantic resolution path

- [x] Extend notification Rule arguments to support an optional semantic resolution.
- [x] Preserve legacy plain-string notification arguments.
- [x] Add `NotificationVal`.
- [x] Compile notification text through `SlotVal`.
- [x] Compile optional resolution as a string.
- [x] Render a real `Notification` object from `NotificationVal`.
- [x] Update `Client::addNotification()` to consume `Notification`.
- [x] Add optional resolution support to `Notification`.
- [x] Render `data-resolution="..."` only when a resolution exists.
- [x] Verify notifications without resolutions render no `data-resolution`.
- [x] Update the MikroTik client Rule to use:
  ```json
  [
    "Software update available:\nVersion {{latest_version}} ({{update_channel}})",
    "install-routeros-update"
  ]
  ```
- [x] Update deterministic dashboard fixture.
- [x] Verify full test suite green.
- [x] Deploy public NOC.
- [x] Verify production page source contains the inert `data-resolution`.
- [x] Spike trusted enhancement from Bredland-served `trusted.js`.
- [x] Verify trusted JS can find `install-routeros-update` and inject an `Update` button.
- [x] Verify harmless click handler on Mac Chrome.
- [x] Verify harmless click handler on iPhone Chrome over WireGuard.

## RouterOS permanent test action

- [ ] Define a permanent, harmless RouterOS script for testing trusted actions.
- [ ] Give it a stable repo-managed name and purpose.
- [ ] Make its only side effect a distinctive RouterOS log entry.
- [ ] Provision it through the Bredland/RouterOS deployment tooling rather than manually treating it as production configuration.
- [ ] Configure it so the restricted Bredland REST identity may invoke it through the previously proven `dont-require-permissions=yes` mechanism.
- [ ] Verify manually from Bredland that invoking it produces exactly the expected log entry.
- [ ] Verify the restricted REST identity still cannot perform arbitrary RouterOS writes.
- [ ] Keep this logging-only script permanently available for later trusted-action slices.

## RouterOS trusted REST provisioning

- [ ] Turn the REST feasibility-spike configuration into repo-managed provisioning.
- [ ] Provision/maintain RouterOS HTTPS REST access for Bredland.
- [ ] Source-restrict REST access to Bredland.
- [ ] Provision a dedicated Bredland REST identity with the experimentally established minimum policy:
  `read,api,rest-api`.
- [ ] Store its credential privately on Bredland; never in the repo or browser.
- [ ] Verify Bredland trusts the RouterOS HTTPS certificate.
- [ ] Verify unauthenticated REST requests fail.
- [ ] Verify the restricted identity can perform the reads Bredland needs.
- [ ] Verify it cannot perform generic privileged writes.
- [ ] Bring the existing nightly `noc-check-routeros-updates` script/scheduler under repo-managed provisioning without redesigning it.

## Bredland trusted action endpoint — harmless first version

- [ ] Decide the narrow HTTP contract for trusted action requests.
- [ ] Extend the existing `trusted_discovery.py` service if that remains the simplest home for the endpoint.
- [ ] Add one endpoint for semantic trusted actions.
- [ ] Accept semantic resolution values only — never RouterOS command text, script names, paths, or arbitrary arguments from the browser.
- [ ] Add an explicit Bredland mapping:
  ```text
  install-routeros-update
      → harmless logging script
  ```
  for the first implementation.
- [ ] Reject unknown resolutions.
- [ ] Reject malformed requests.
- [ ] Ensure the endpoint is reachable only through the trusted Bredland path.
- [ ] Preserve the existing restricted CORS/origin policy.
- [ ] Add deterministic Python tests for accepted and rejected requests.
- [ ] Add tests proving browser-controlled input cannot select an arbitrary RouterOS script.
- [ ] Add appropriate request/result logging on Bredland without logging secrets.

## First browser → Bredland → MikroTik proof

This stage is deliberately **not the final authorization protocol**. Its purpose is to prove the complete mechanical path using the harmless logging script.

- [ ] Modify `trusted.js` so the injected `Update` button can send the semantic resolution to Bredland.
- [ ] Send only:
  ```text
  install-routeros-update
  ```
  plus whatever minimal request envelope Bredland requires.
- [ ] Have Bredland map that semantic resolution to the harmless RouterOS logging script.
- [ ] Press the button from the trusted dashboard.
- [ ] Verify Bredland receives the request.
- [ ] Verify MikroTik executes the harmless script.
- [ ] Verify the distinctive log entry appears.
- [ ] Verify no action is possible with WireGuard/LAN trusted access absent.

## Final trusted request / authorization protocol

Before replacing the harmless proof with the real update action:

- [ ] Design the tightly scoped trusted request protocol.
- [ ] Ensure possession of public NOC markup or knowledge of `install-routeros-update` grants no authority by itself.
- [ ] Ensure trusted-mode UI visibility grants no authority by itself.
- [ ] Ensure a bare browser POST grants no authority by itself.
- [ ] Design short-lived authorization material issued only over the trusted channel.
- [ ] Make authorization scoped specifically to the semantic resolution being requested.
- [ ] Make authorization single-use / non-replayable.
- [ ] Give it a deliberately short lifetime.
- [ ] Bind validation to whatever contextual information is necessary without creating a generic session/auth framework.
- [ ] Reject expired authorization.
- [ ] Reject replayed authorization.
- [ ] Reject authorization used for a different resolution.
- [ ] Reject malformed/tampered authorization.
- [ ] Add deterministic tests for all of the above.
- [ ] Keep the implementation specific to BRD-032; do not grow a generic action registry, queue, administration API, or general-purpose control framework.

## Bredland action validation

Before crossing the RouterOS privilege boundary:

- [ ] Independently verify that `install-routeros-update` is still reasonable/current.
- [ ] Query RouterOS state from Bredland rather than trusting the notification or browser.
- [ ] Verify that an update is actually available.
- [ ] Verify the relevant update state is suitable for installation.
- [ ] Reject the action cleanly if circumstances have changed since the dashboard rendered.
- [ ] Define clear success/failure responses for the trusted browser UI.
- [ ] Add tests for stale/no-longer-valid action requests.

## Trusted UI

- [ ] Replace the disposable spike code in `trusted.js` with tested production code.
- [ ] Recognise only explicitly supported `data-resolution` values.
- [ ] Inject the action button only for supported resolutions.
- [ ] Make enhancement idempotent so repeated execution cannot create duplicate buttons.
- [ ] Style the button in Bredland-served `trusted.css`.
- [ ] Make the button visually fit the existing notification panel.
- [ ] Add an explicit confirmation step before sending a privileged request.
- [ ] Give the user useful pending/success/failure feedback.
- [ ] Prevent accidental repeat submission while an action is in flight.
- [ ] Ensure unsupported resolution metadata remains inert.
- [ ] Add deterministic JS tests for enhancement and button behaviour.
- [ ] Verify no trusted action implementation leaks into public NOC JS/CSS.

## Real RouterOS update action

- [ ] Design the permanent `noc-install-routeros-update` RouterOS script.
- [ ] Keep the script narrowly scoped to installing the already available RouterOS update.
- [ ] Initially implement it with a safety catch: same permanent script name and permissions, but only log that an update would be installed.
- [ ] Provision it through repo-managed RouterOS deployment.
- [ ] Set `dont-require-permissions=yes` only on this explicitly privileged script.
- [ ] Change Bredland's semantic mapping:
  ```text
  install-routeros-update
      → noc-install-routeros-update
  ```
- [ ] Verify the restricted REST identity can invoke `noc-install-routeros-update` while the safety catch is enabled.
- [ ] Verify the safety-catch log entry appears and no update/reboot occurs.
- [ ] Verify the restricted identity still cannot perform arbitrary RouterOS writes.
- [ ] Run the complete trusted-action flow against the safety-caught script.
- [ ] Keep the separate harmless logging script permanently available as a diagnostic/test tool.
- [ ] Run all automated tests with the safety catch still enabled.
- [ ] Immediately before the final live acceptance test, replace only the safety-caught script body with the real RouterOS update operation.
- [ ] Verify the deployed script body is the intended armed version.
- [ ] Perform the final live acceptance test without unrelated changes in between.

## Final live acceptance test

- [ ] Confirm an actual RouterOS update is available.
- [ ] Confirm the public NOC displays the existing update notification.
- [ ] Confirm the notification carries:
  ```html
  data-resolution="install-routeros-update"
  ```
- [ ] Access the NOC through a supported trusted context.
- [ ] Confirm trusted assets load and the `Update` button appears.
- [ ] Press `Update`.
- [ ] Confirm the explicit confirmation UI.
- [ ] Confirm the trusted request is accepted exactly once.
- [ ] Confirm Bredland independently validates that the update remains appropriate.
- [ ] Confirm Bredland invokes only `noc-install-routeros-update`.
- [ ] Observe RouterOS install/reboot.
- [ ] Confirm RouterOS returns.
- [ ] Confirm telemetry resumes.
- [ ] Confirm telemetry reports the new RouterOS version.
- [ ] Confirm the update notification disappears naturally because the Rule no longer matches.
- [ ] Confirm a replay of the original authorized request is rejected.
- [ ] Confirm ordinary/untrusted NOC behaviour remains unchanged.

## Cleanup and documentation

- [ ] Remove the temporary `alert()` spike from `trusted.js`.
- [ ] Ensure no temporary test credentials, certificates, files, mappings, or debug endpoints remain.
- [x] Rotate the MikroTik telemetry token exposed during the earlier REST feasibility spike.
- [ ] Document the trusted action request contract.
- [ ] Document the permanent logging-only RouterOS test action.
- [ ] Document the RouterOS REST account/policy/source restriction.
- [ ] Document operational deployment/restart steps for trusted assets/service.
- [ ] Update BRD-032 documentation to reflect the final two-shape notification syntax rather than the earlier object proposal.
- [ ] Run the complete PHP, shell, JavaScript, and Python suite.
- [ ] Run deployment rehearsal/dry-run where supported.
- [ ] Deploy final BRD-032.
- [ ] Perform final production smoke checks.
- [ ] Submit for review.

## Architectural invariants

- `data-resolution` is metadata, not authorization.
- Trusted UI visibility is not authorization.
- A browser request is not permission to execute.
- The browser supplies semantic intent only.
- Bredland owns semantic resolution → privileged action mapping.
- Bredland independently verifies that an action remains appropriate before execution.
- RouterOS privilege lives only in explicitly provisioned, narrowly scoped scripts.
- The restricted RouterOS REST identity has no generic write authority.
- Public NOC assets contain no privileged implementation.
- Trusted JavaScript and CSS remain Bredland-served.
- BRD-032 implements one concrete action, not a generic remote-control framework.

Branch: `sec/BRD-032_first-trusted-dashboard-action`
