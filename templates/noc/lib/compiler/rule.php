<?php

class Rule {
    private $predicate;
    private $effect;

    public function __construct($predicate, $effect) {
        $this->predicate = $predicate;
        $this->effect = $effect;
    }

    public function predicate() {
        return $this->predicate;
    }

    public function effect() {
        return $this->effect;
    }
}
