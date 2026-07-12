<?php

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
