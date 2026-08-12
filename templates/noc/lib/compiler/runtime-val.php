<?php
/**
 * A compiled value that can be resolved in the context of a heartbeat.
 *
 * Implementations share the render($heartbeat) contract so callers can treat
 * them polymorphically. Some implementations, such as FieldVal, use the
 * heartbeat to obtain their value; literal implementations may ignore it.
 */
interface RuntimeVal {
    public function render($heartbeat);
}