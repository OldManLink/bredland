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

