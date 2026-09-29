# BRD-038 TODO — Script manual Bredland TLS certificate renewal

## Purpose

Build a safe, repeatable, manually initiated certificate-renewal workflow for Bredland.

The operator still decides **when** renewal happens.

The implementation should nevertheless keep the renewal mechanics clean enough that a future automation slice could reuse them rather than requiring a rewrite.

Guiding principle:

> Manual orchestration today; reusable non-interactive primitives underneath where the real infrastructure allows it.

---

## Security / trust boundary

The Mac remains the orchestrator.

Control direction should stay:

```text
Mac → Bredland
Mac → Oderland / cPanel
```

Do not require:

```text
Bredland → Mac
```

Do not place Mac SSH credentials on Bredland merely to simplify orchestration.

Do not give Bredland broader DNS/cPanel authority than it already has.

---

## Intended high-level flow

```text
operator starts command on Mac
        ↓
Mac creates unique run-id
        ↓
Mac asks Bredland to start renewal for run-id
        ↓
Bredland starts coordinator + certbot
        ↓
certbot auth hook creates challenge request
        ↓
Bredland start command returns when request is ready
        ↓
Mac reads challenge request
        ↓
Mac publishes DNS TXT record through Oderland/cPanel
        ↓
Mac waits until exact TXT value is visible
        ↓
Mac signals Bredland to continue
        ↓
certbot resumes
        ↓
Mac calls Bredland wait command
        ↓
wait command returns only when certbot has finished
        ↓
Mac validates issued certificate
        ↓
Mac provisions certificate/key
        ↓
trusted service reload/restart
        ↓
Mac independently verifies served certificate
        ↓
Mac removes DNS TXT record
        ↓
normal telemetry reports new expiry
        ↓
existing NOC warning clears naturally
```

---

## Bredland renewal protocol

Treat the Bredland side as a small protocol rather than as an interactive terminal session.

Candidate commands:

```text
start-renewal <run-id>
continue-renewal <run-id>
wait-renewal <run-id>
```

### `start-renewal <run-id>`

Responsibilities:

- validate the run-id;
- create run-specific state;
- start the renewal coordinator in the background;
- coordinator starts Certbot;
- Certbot invokes the manual DNS auth hook;
- auth hook writes the challenge request atomically;
- block locally on Bredland until the request is ready;
- return success only when the request can safely be consumed by the Mac;
- return nonzero if setup fails.

The SSH connection should not remain attached to Certbot for the full renewal.

### `continue-renewal <run-id>`

Responsibilities:

- verify the run exists and is in the expected state;
- create the run-specific continuation marker atomically;
- return immediately.

The Certbot auth hook waits for this marker and exits `0` when it appears.

### `wait-renewal <run-id>`

Responsibilities:

- block locally on Bredland until the coordinator records a terminal state;
- return `0` for successful Certbot completion;
- return a meaningful nonzero status for failure;
- expose enough diagnostics for the Mac-side script/operator to understand the failed phase;
- do not leak secrets.

The Mac should not poll repeatedly over SSH.

---

## Run-specific rendezvous state

The Mac creates the run-id before invoking Bredland.

Example:

```text
7f9a6e4c1d2b
```

Bredland uses that run-id to create isolated state, for example:

```text
/run/brd-038/7f9a6e4c1d2b/
```

Candidate contents:

```text
request
continue
status
exit-code
log
```

The exact directory and file names may change during implementation.

### Why run-specific state matters

Never use a global marker such as:

```text
/tmp/certbot-continue
```

A stale marker from an earlier failed run could accidentally release a later Certbot challenge.

Each run must only react to its own state.

### Request publication

The auth hook should write the challenge request atomically.

For example:

```text
request.tmp
    ↓
mv request.tmp request
```

The Mac must never read a half-written request.

Candidate request contents:

```text
identifier=nocster-73.arcanel.se
record=_acme-challenge.nocster-73.arcanel.se
validation=<ACME challenge value>
```

The exact representation should emerge from the tests and real Certbot hook environment.

### Continuation

The auth hook waits for:

```text
/run/brd-038/<run-id>/continue
```

with a bounded timeout.

When the marker appears:

- verify the run is still valid;
- clean up or consume the marker deliberately;
- exit `0`;
- Certbot resumes.

On timeout or invalid state:

- exit nonzero;
- coordinator records failure.

---

## Certbot integration

Do not scrape interactive Certbot terminal output or simulate pressing `<Enter>`.

Prefer Certbot's manual DNS hook interface.

Expected model:

```text
certbot
    ↓
manual-auth-hook
    ↓
hook receives challenge environment
    ↓
hook writes request
    ↓
hook waits for continue marker
    ↓
hook exits 0
    ↓
certbot performs CA validation
```

Investigate and confirm the real environment variables supplied by the Certbot version installed on Bredland before committing to names or formats.

The coordinator layer should own Certbot for the complete run and record its terminal status.

---

## Mac-side orchestration

The Mac script should be testable without real Certbot, DNS, certificates, or Bredland production state.

Candidate orchestration:

```text
generate run-id

ssh bredland start-renewal <run-id>

read request from Bredland

publish TXT record

wait for exact TXT value to be visible

ssh bredland continue-renewal <run-id>

ssh bredland wait-renewal <run-id>

validate issued files

provision certificate/key

reload/restart trusted service

verify served certificate independently

remove TXT record
```

The Mac remains the only component that knows how to coordinate both Bredland and Oderland/cPanel.

---

## DNS publication

Investigate the actual Oderland/cPanel mechanism before coding against assumptions.

Need to determine:

- exact cPanel API available to this account;
- authentication method;
- minimum required authority;
- exact record-name form cPanel expects;
- add-TXT operation;
- delete-TXT operation;
- whether an existing matching record must be handled;
- how failure is reported.

Do not broaden credential authority merely to make scripting easier.

### DNS visibility

After publishing the TXT record, poll for the exact expected value with a bounded timeout.

Candidate progression:

```text
publish TXT
    ↓
query authoritative DNS
    ↓
optionally query a public resolver
    ↓
continue only when exact token is visible
```

Do not release Certbot merely because cPanel accepted the update.

---

## Certificate validation and activation

Separate these states explicitly:

```text
issuance succeeded
```

and:

```text
activation succeeded
```

After Certbot finishes successfully:

1. inspect the newly issued certificate;
2. verify expected identity;
3. verify expiry is later than the currently served certificate;
4. identify the exact certificate/key files;
5. provision them where trusted-discovery expects them;
6. perform the minimum reload/restart necessary;
7. independently inspect the certificate actually served by Bredland;
8. only then call the renewal successful.

Do not report success merely because Certbot wrote certificate files.

---

## DNS cleanup

Remove the temporary `_acme-challenge` TXT record only after issuance and activation/served-certificate verification have succeeded, unless failure recovery proves that another cleanup policy is safer.

Cleanup must be deliberate.

Do not destroy useful evidence automatically if a failure still needs investigation.

---

## Testing strategy

The Bredland renewal protocol makes the Mac orchestration testable before touching real Certbot.

### Fake Bredland coordinators

Create deterministic test doubles that implement the same commands:

```text
start-renewal
continue-renewal
wait-renewal
```

They can simulate Certbot by:

- creating a request after a controlled delay;
- waiting for the continuation marker;
- returning `0`;
- returning specific nonzero statuses;
- producing malformed request data;
- never producing a request;
- timing out;
- failing after continuation;
- recording terminal state.

The Mac-side orchestration should be developed against these fakes first.

### Candidate Mac-side scenarios

Test at least:

- happy path;
- start failure;
- delayed request;
- malformed request;
- unexpected hostname;
- DNS publication failure;
- DNS propagation timeout;
- continue failure;
- Certbot failure after continue;
- wait failure;
- certificate validation failure;
- provisioning failure;
- service reload/restart failure;
- served-certificate verification failure;
- DNS cleanup failure.

Do not contact production DNS or the production CA from automated tests.

---

## Safe ACME development

Do not use repeated production issuance as the development loop.

Preferred layers:

```text
fake coordinator/tests
        ↓
Certbot staging / test CA
        ↓
real production issuance only when workflow is already boring
```

Use the staging environment for real ACME challenge rehearsals.

Use production issuance sparingly to avoid exhausting CA rate limits.

---

## Investigation checklist

Before implementing real integration:

- [ ] Inspect current production certificate.
- [ ] Record current expiry.
- [ ] Confirm exact certificate identity.
- [ ] Recover/confirm the exact current Certbot invocation.
- [ ] Confirm Certbot version on Bredland.
- [ ] Confirm manual-auth-hook and cleanup-hook behavior for that version.
- [ ] Confirm where Certbot currently writes certificate/key files.
- [ ] Inspect the trusted-discovery service configuration.
- [ ] Confirm exactly which certificate/key paths it consumes.
- [ ] Determine whether activation needs reload or restart.
- [ ] Confirm how the served certificate can be independently inspected from the Mac.
- [ ] Confirm actual Oderland/cPanel DNS API capabilities.
- [ ] Confirm cPanel authentication method.
- [ ] Confirm TXT record naming behavior.
- [ ] Confirm add/delete TXT operations.
- [ ] Confirm authoritative DNS servers/resolvers useful for propagation checks.

---

## TDD sequence

### 1. Define the Bredland protocol contract

Write deterministic tests for:

```text
start-renewal
continue-renewal
wait-renewal
```

Do not call real Certbot yet.

### 2. Implement fake coordinator

Provide the minimum fake Bredland-side behavior needed to exercise the protocol.

### 3. Drive Mac orchestration against the fake

Build the Mac workflow through the full happy path first.

### 4. Add Mac-side failure paths

Introduce one failure at a time.

Keep error messages phase-specific and boring.

### 5. Implement real Bredland coordinator

Replace fake Certbot behavior behind the already-tested protocol.

### 6. Integrate Certbot hook

Use the run-specific request/continue rendezvous.

Exercise with staging first.

### 7. Integrate real DNS publication

Use the actual demonstrated Oderland/cPanel mechanism.

### 8. Add certificate validation/provisioning

Keep issuance and activation separate.

### 9. Add independent served-certificate verification

Do not declare success before this passes.

### 10. Rehearse complete workflow with staging

Run the whole Mac-driven process end to end without production issuance.

### 11. Perform production renewal

Only after staging behavior is understood and deterministic.

### 12. Verify NOC convergence

Confirm:

```text
tls_cert_expires_at
```

moves to the new expiry and the existing certificate warning clears naturally.

---

## Design constraints

- Manual operator initiation remains mandatory in BRD-038.
- Do not add a scheduler.
- Do not add an automatic NOC-triggered renewal.
- Do not store Mac SSH credentials on Bredland.
- Do not make Bredland initiate connections back to the Mac.
- Do not add generic remote-operation infrastructure.
- Do not automate around behavior we have not yet observed.
- Prefer explicit state and exit codes over terminal scraping.
- Prefer bounded waits over indefinite polling.
- Prefer run-specific state over shared global markers.
- Keep secrets out of the repository and logs.
- Keep the implementation boring enough that future automation can invoke it unchanged.

---

## Definition of done

BRD-038 is complete when:

- the operator can start one command on the Mac;
- that command drives the real renewal workflow safely;
- the Bredland protocol is deterministic and independently testable;
- DNS publication and propagation are verified before Certbot continues;
- Certbot completion is observed without SSH polling loops;
- issuance and activation failures are distinguishable;
- the served certificate is independently verified;
- temporary DNS state is cleaned up deliberately;
- the existing telemetry reports the new expiry;
- the existing NOC warning clears naturally;
- the complete repository test suite remains green;
- the next renewal can be performed from repository documentation without reconstructing commands from chat history.

---

## Future automation

BRD-038 does not automate the decision to renew.

However, avoid designs that would force a future automation slice to replace the renewal mechanics.

A future slice should ideally be able to replace only:

```text
human decides to run Mac command
```

with some trusted trigger, while reusing the same:

```text
start
request
continue
wait
validate
provision
activate
verify
cleanup
```

workflow.

That future decision is explicitly out of scope for BRD-038.
