<?php
require_once __DIR__ . '/runtime-val.php';
/**
 * A runtime-renderable part of an interpolated slot value.
 *
 * SlotVal composes SlotPart instances and renders each one against the same
 * heartbeat before concatenating their results. Implementations therefore
 * inherit the RuntimeVal contract.
 *
 * Typical implementations are StrVal for literal text and FieldVal for
 * heartbeat-backed values.
 */
 interface SlotPart extends RuntimeVal {
    public function render($heartbeat);
}