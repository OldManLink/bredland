# BRD-022 TODO

Remaining work before dashboard notifications are considered complete.

## Runtime polish

### Client

- ✅ Review PHPDoc for the runtime API.
- ✅ Review exception messages for consistency and clarity.
- ✅ Remove any remaining dead execute-era code.
- [ ] Add any missing regression tests discovered during cleanup.

### Rules

- ✅ Add an end-to-end regression test covering multiple rules evaluated during a single render.

## Dashboard integration

### Health

- ✅ Populate client health during rendering.
- [ ] Expose client health to the dashboard.

### Notifications

- ✅ Populate notifications during rendering.
- [ ] Expose notifications to the dashboard.
- [ ] Render notification badges.
- [ ] Implement notification popover.
- [ ] Ensure notifications clear automatically when their conditions are no longer true.

## Polish

- ✅ Review naming and documentation throughout the runtime/compiler boundary.
- ✅ Remove any obsolete comments referring to the old execute phase.
- ✅ Review the public runtime API for consistency.

---

## Future work (not part of BRD-022)

These ideas intentionally remain out of scope for this slice.

- [ ] Generalise the health evaluation pipeline for future health rules.
- [ ] Support multiple notification severities.
- [ ] Support richer notification presentation (icons, timestamps, grouping) if genuine use cases emerge.
- [ ] Consider notification history once the history endpoint exists.

---

## Notes

### Runtime philosophy

- Compile once.
- Render lazily.
- Evaluate rules eagerly.
- Keep heartbeat rendering side-effect free except for explicitly modelled runtime state (health and notifications).

### Design rule

The compiler owns structure.

The runtime owns behaviour.

Avoid introducing new execution phases unless the domain genuinely requires them.