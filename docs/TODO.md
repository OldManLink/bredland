# BRD-016 TODO

Remaining work before the declarative rules compiler is considered complete.

## Language closure

### Rule

- ✅ Reject unknown rule attributes (only `when` and `then` are permitted).
- ✅ Add regression tests for unsupported rule attributes (e.g. `else`).

### Predicate

- ✅ Reject unknown predicate attributes.
- ✅ Validate that the predicate receiver exists in the client schema.
- ✅ Validate that the selected comparator is compatible with the receiver's declared `valueType`.
- ✅ Add regression tests for all of the above.

### Effect

- ✅ Reject unknown effect attributes.
- [ ] Validate notification message placeholders (`{{field}}`) against the client schema.
- [ ] Add regression tests for placeholder validation.
- ✅ Add regression tests for unknown effect attributes.

## Client compilation

- ✅ Refactor `client-descriptions.test.php` to use the production compiler.
- ✅ Replace duplicated validation logic with compiler diagnostics.
- ✅ Assert that valid client descriptions compile without warnings or errors.

## Compiler architecture

- ✅ Introduce `compile_client()`.
- ✅ Pass the client schema into the compiler (loaded by the caller, not by the compiler itself).
- ✅ Keep the compiler completely independent of filesystem I/O.

## Polish

- [ ] Remove any remaining duplicated validation logic from tests.
- [ ] Review compiler diagnostics for consistency and readability.
- [ ] Add any missing regression tests discovered during implementation.

---

## Future work (not part of BRD-016)

These ideas intentionally remain out of scope for this slice.

- [ ] Introduce `compile_schema()`, if the schema itself develops behaviour beyond being a validated data structure.
- [ ] Support additional predicate comparators (`greaterThan`, `notEquals`, etc.) as real use cases emerge.
- [ ] Support richer predicate composition (`and`, `or`) if the language genuinely requires it.
- [ ] Extend notification placeholders with formatting helpers (for example `{{free_memory_human}}`).

---

## Notes

### Compiler philosophy

- Keep the compiler pure: JSON + Schema → CompilationResult.
- Keep all filesystem access outside the compiler.
- Prefer many small compiler stages (`compile_rule`, `compile_predicate`, `compile_effect`) over one large function.
- Every compiler stage should either produce a valid domain object or a diagnostic, never both.

### Design rule

Resist adding language features until the domain genuinely requires them.

One predicate → one effect has proven to be expressive, simple and easy to reason about. Keep it that way unless real-world requirements demonstrate otherwise.