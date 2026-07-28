<?php
/**
 * A compiled object that can render itself without runtime context.
 *
 * Unlike RuntimeVal, implementations do not depend on a heartbeat or other
 * runtime input. Their compiled state contains everything required to produce
 * their runtime representation.
 *
 * Typical implementations are MethodVal, which renders a method name, and
 * OpVal, which renders an operator as a callable.
 */
interface Renderable {
    public function render();
}
