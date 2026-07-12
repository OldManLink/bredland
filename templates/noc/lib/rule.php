<?php

class Predicate {
    private $receiver;
    private $comparator;
    private $argument;

    public function __construct($receiver, $comparator, $argument) {
        $this->receiver = $receiver;
        $this->comparator = $comparator;
        $this->argument = $argument;
    }

    public function receiver() {
        return $this->receiver;
    }

    public function comparator() {
        return $this->comparator;
    }

    public function argument() {
        return $this->argument;
    }
}

class Effect {
    private $type;
    private $argument;

    public function __construct($type, $argument) {
        $this->type = $type;
        $this->argument = $argument;
    }

    public function type() {
        return $this->type;
    }

    public function argument() {
        return $this->argument;
    }
}

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
