# BRD-022 TODO

## Objectify the NOC

### Guardrails

- ✅ Confirm the complete test suite is green before starting.
- ✅ Add characterisation coverage for the current dashboard HTML where needed.
- [ ] Add endpoint-level coverage for the current telemetry request and response behaviour where needed.
- ✅ Preserve PHP 5.5 compatibility throughout.
- ✅ Preserve the current rendered HTML, telemetry format and HTTP behaviour unless deliberately changed by BRD-022.
- [ ] Keep each refactoring step small enough to leave the complete test suite green.
- [ ] Do not introduce classes that merely reproduce incidental HTML wrapper elements.
- [ ] Ensure that neither production entry point calls standalone functions when the refactoring is complete.

---

## 1. Establish the NOC orchestration boundary

- ✅ Define the responsibility of `Noc`.
- ✅ Make `Noc` responsible for coordinating dashboard construction and rendering.
- ✅ Decide which dependencies `Noc` receives explicitly.
- [ ] Add focused tests for `Noc`.
- ✅ Change `index.template.php` to construct and invoke `Noc`.
- [ ] Ensure that `index.template.php` no longer calls standalone functions.

Target shape:

```php
$noc = new Noc(...);
echo $noc->render();
```

The entry point may retain:

- configuration loading;
- required class-file loading;
- the static document `<head>`;
- construction of the object graph.

It should contain no application logic.

**Initially migrate behaviour before refactoring behaviour.** The first goal is to move ownership into Noc while preserving the current implementation as closely as practical.

---

## 2. Objectify dashboard data loading

Identify responsibilities currently hidden behind functions such as:

- `load_clients()`;
- `read_client_file()`;
- `heartbeat_from_jsonl()`;
- `latest_jsonl_line()`.

Introduce cohesive classes based on the responsibilities discovered.

Likely candidates:

- [ ] `ClientLoader`
- [ ] `HeartbeatLoader`
- [ ] `Heartbeat`

Names remain provisional until responsibilities are clear.

### Client loading

- [ ] Move client-description discovery into a class.
- [ ] Move client-file reading and validation into that class or a dedicated collaborator.
- [ ] Return compiled `Client` objects rather than dashboard-specific arrays where practical.
- [ ] Preserve deterministic client ordering.
- [ ] Preserve the current handling of invalid client descriptions.
- [ ] Migrate existing function tests to the new class API.
- [ ] Add regression tests for any behaviour not already covered.

### Heartbeat loading

- [ ] Encapsulate locating and reading the latest heartbeat.
- [ ] Represent the loaded heartbeat and its age coherently.
- [ ] Avoid exposing heartbeat filenames to rendering classes.
- [ ] Preserve behaviour for missing, empty and malformed heartbeat files.
- [ ] Preserve the stateless-client principle.
- [ ] Migrate existing heartbeat-loading tests to the new class API.

---

## 3. Objectify dashboard presentation logic

Identify responsibilities currently hidden behind functions such as:

- `heartbeat_health_colour()`;
- `format_heartbeat_age()`;
- `display_client_field()`.

- [ ] Move heartbeat age and health presentation out of `index.template.php`.
- ✅ Move client-field rendering out of `index.template.php`.
- [ ] Prefer behaviour on an existing domain object when it naturally belongs there.
- [ ] Introduce a dedicated presenter or renderer only where the behaviour does not belong on the domain object.
- [ ] Preserve existing escaping at the final HTML boundary.
- [ ] Add tests for healthy, warning, critical and unavailable presentation states.
- [ ] Add tests for all supported field formatters and value types.

---

## 4. Introduce semantic dashboard renderers

Create rendering classes for dashboard concepts, not for every DOM wrapper.

Likely candidates:

- ✅ `Dashboard`
- [ ] `ClientCard`
- [ ] `TelemetryDrawer`
- [ ] `RefreshIndicator`, only if it has enough behaviour to justify a class

Do not create classes solely because the current CSS contains elements such as:

- `cards-row`;
- `card-slot`;
- `card-container`;
- `led`.

Those elements may be emitted internally by a semantic renderer.

### Dashboard

- [ ] Render the dashboard body from a collection of clients.
- ✅ Own dashboard-level composition.
- [ ] Render clients in their configured order.
- [ ] Add tests for an empty dashboard and a dashboard containing multiple clients.

### Client card

- [ ] Render the client title.
- [ ] Render heartbeat age and health state.
- [ ] Render configured fields.
- [ ] Render notification state introduced by BRD-022.
- [ ] Render the telemetry-drawer handle.
- [ ] Preserve current classes and `data-*` attributes required by JavaScript and CSS.
- [ ] Add focused rendering tests.

### Telemetry drawer

- [ ] Render the latest raw telemetry safely.
- [ ] Preserve the existing `<template>` contract used by JavaScript.
- [ ] Handle unavailable telemetry explicitly.
- [ ] Add focused rendering tests.

### Refresh indicator

- [ ] Leave the current markup in the entry-point body unless extracting it makes the design clearer.
- [ ] Extract it only if it gains behaviour or meaning beyond a fixed fragment.

---

## 5. Reduce `index.template.php` to a true entry point

- [ ] Remove all loops and conditionals concerned with dashboard content.
- [ ] Remove direct filesystem access.
- [ ] Remove direct heartbeat processing.
- [ ] Remove direct field formatting.
- [ ] Remove calls to all standalone application functions.
- [ ] Retain only bootstrap/configuration code and static document framing.
- ✅ Render the dashboard through `Noc`.

Desired body shape:

```php
<body>
    <?= $noc->render() ?>
</body>
```

The exact division between the static document frame and `Dashboard` may be adjusted if a cleaner boundary emerges.

---

## 6. Establish the telemetry endpoint orchestration boundary

- [ ] Introduce `TelemetryEndpoint`.
- [ ] Make it responsible for coordinating one telemetry request.
- [ ] Add endpoint-level tests before replacing the procedural entry point.
- [ ] Preserve all current status codes and response bodies.
- [ ] Preserve the current plain-text content type.
- [ ] Preserve the current timestamp, remote-address and JSONL behaviour.

Likely collaborators:

- [ ] `Authenticator`
- [ ] `SchemaLoader`
- [ ] `RecordBuilder`
- [ ] `Storage`

Additional classes should be introduced only when they represent a coherent responsibility.

Target shape:

```php
$endpoint = new TelemetryEndpoint(...);
$endpoint->handle($_SERVER, $_POST);
```

---

## 7. Objectify telemetry request handling

### Request validation

Replace the local `param()` function with object behaviour.

- [ ] Validate the HTTP method.
- [ ] Validate required parameters.
- [ ] Preserve empty-string handling.
- [ ] Preserve current error messages.
- [ ] Avoid reading `$_POST` throughout the object graph.
- [ ] Pass request data explicitly after the entry-point boundary.

A dedicated request object may be introduced if it simplifies the design; it is not required merely to wrap an array.

### Responses

Replace the local `respond()` function with endpoint behaviour.

- [ ] Centralise status, content type and body emission.
- [ ] Keep termination behaviour at the outermost HTTP boundary.
- [ ] Make response outcomes testable without terminating the test process.
- [ ] Preserve the terminating newline in response bodies.

Do not build a general-purpose response framework unless the endpoint genuinely needs one.

---

## 8. Objectify telemetry authentication

Replace procedural authentication with an explicit collaborator.

- [ ] Introduce `Authenticator` or another appropriately named class.
- [ ] Encapsulate host-token lookup and secure comparison.
- [ ] Preserve unknown-host and invalid-token behaviour.
- [ ] Migrate the existing authentication tests.
- [ ] Keep deployment secrets outside version control.
- [ ] Do not make the configuration object responsible for authentication behaviour.

---

## 9. Objectify telemetry record construction

Identify responsibilities currently hidden behind:

- `select_fields()`;
- `load_record_schema()`;
- `build_record()`;
- compatibility and type-conversion helpers.

### Schema loading

- [ ] Introduce a schema-loading responsibility.
- [ ] Preserve host-specific schema lookup.
- [ ] Preserve malformed and missing-schema behaviour.
- [ ] Migrate existing schema tests.

### Field selection

- [ ] Decide whether field selection belongs in `RecordBuilder` or deserves a separate collaborator.
- [ ] Preserve reserved-field protection.
- [ ] Preserve requested-field ordering and validation.
- [ ] Avoid creating a class for field selection unless it has a clear independent contract.

### Record building

- [ ] Introduce `RecordBuilder`.
- [ ] Preserve schema-version handling.
- [ ] Preserve all supported value conversions.
- [ ] Preserve complete-record validation.
- [ ] Preserve existing exception types and messages.
- [ ] Migrate existing record tests to the class API.

---

## 10. Objectify telemetry storage

Replace procedural storage operations with an explicit storage boundary.

Likely responsibility:

- [ ] `Storage`

- [ ] Ensure the data directory exists.
- [ ] Construct the daily JSONL filename.
- [ ] Append a complete record atomically enough for the current deployment model.
- [ ] Preserve host filename validation and path safety.
- [ ] Preserve JSON encoding and newline behaviour.
- [ ] Migrate existing storage tests.
- [ ] Keep archive and consolidation responsibilities outside this class unless their existing design clearly belongs with it.

Prefer a name more specific than `FileLoader` or `FileStorage` if the final responsibility warrants it.

---

## 11. Reduce `telemetry.endpoint.template.php` to a true entry point

- [ ] Remove `respond()`.
- [ ] Remove `param()`.
- [ ] Remove direct authentication.
- [ ] Remove direct schema loading.
- [ ] Remove direct record construction.
- [ ] Remove direct storage operations.
- [ ] Remove calls to all standalone application functions.
- [ ] Retain only configuration loading, class loading, object construction and endpoint invocation.

Desired shape:

```php
$endpoint = new TelemetryEndpoint(...);
$endpoint->handle($_SERVER, $_POST);
```

---

## 12. Review configuration boundaries

The configuration template may remain declarative PHP data.

- [ ] Keep host tokens and the data directory outside the web root.
- [ ] Decide whether `Noc` and `TelemetryEndpoint` should receive raw configuration values or a small configuration object.
- [ ] Introduce a configuration class only if it provides validation or meaningful behaviour.
- [ ] Do not objectify constants and arrays merely for stylistic purity.
- [ ] Preserve deployment-time placeholder substitution.
- [ ] Preserve the rule that rendered configuration files are never committed.

Pure OO entry points do not require every configuration value to become an object.

---

## 13. Retire obsolete procedural APIs

Only remove functions after all production and test callers have migrated.

Review standalone functions reported in:

- `auth.php`;
- `client.php`;
- `compatibility.php`;
- `exports.php`;
- `record.php`;
- `storage.php`;
- `telemetry.php`;
- compiler utilities.

For each function:

- [ ] Move behaviour to an existing class where it naturally belongs.
- [ ] Create a new class only for a coherent responsibility.
- [ ] Retain genuinely generic, stateless utility functions where a class would add no clarity.
- [ ] Remove obsolete function files when they become empty.
- [ ] Remove obsolete `require_once` statements.
- [ ] Confirm there are no hidden production callers.
- [ ] Run the complete suite after each removal.

The goal is not zero standalone functions everywhere.

The hard requirement for this slice is:

> Neither `index.php` nor `telemetry.php` calls standalone application functions.

---

## 14. Re-run the architecture report

- [ ] Run the compiler/library architecture report after objectification.
- [ ] Review all standalone classes.
- [ ] Review all standalone functions.
- [ ] Review trait and interface relationships.
- [ ] Confirm that `Noc` is now a genuine orchestration object.
- [ ] Confirm that `TelemetryEndpoint` is now a genuine orchestration object.
- [ ] Document intentional exceptions in the relevant code.
- [ ] Add missing regression tests discovered during the review.

Future work, outside this slice:

- Markdown output from the architecture-report tool.
- A generated architecture document under `docs/`.
- CI verification that the generated document is current.

---

## 15. Complete BRD-022 dashboard notifications

On the new OO rendering path:

- [ ] Expose client health to the dashboard.
- [ ] Expose client notifications to the dashboard.
- [ ] Render the notification badge on the appropriate client card.
- [ ] Render the notification popover.
- [ ] Include a closing `X`.
- [ ] Close the popover when tapping outside it.
- [ ] Do not close it when tapping inside it.
- [ ] Allow text inside it to be selected and copied.
- [ ] Clear notifications when the triggering condition is no longer true.
- [ ] Clear transient display state on page reload.
- [ ] Keep notification state derived rather than persistently dismissed.
- [ ] Add rendering and browser-facing regression coverage where practical.

---

## 16. Final verification

- [ ] Run all PHP unit and integration tests.
- [ ] Run tests under PHP 5.5.38.
- [ ] Run the PHP 5.6 canary.
- [ ] Run `php -l` across the repository.
- [ ] Run shell tests.
- [ ] Run the architecture report.
- [ ] Confirm both entry points call no standalone application functions.
- [ ] Compare rendered dashboard HTML before and after the refactoring.
- [ ] Exercise the telemetry endpoint success path.
- [ ] Exercise all expected telemetry error responses.
- [ ] Rehearse deployment.
- [ ] Deploy the dashboard and telemetry endpoint together where their contracts require it.
- [ ] Perform production smoke tests.
- [ ] Confirm the production NOC remains boring.