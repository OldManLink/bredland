<?php

class Noc {
    private $value;

    public static function compilable_methods() {
        return array(
            'setValue' => StrVal::class
        );
    }


    public function __construct($value) {
        $this->value = $value;
    }

    public function value() {
        return $this->value;
    }

    public function setValue($value) {
        $this->value = $value;
    }
}