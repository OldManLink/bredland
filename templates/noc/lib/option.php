<?php

class Option {
    private $values;

    private function __construct($values) {
        $this->values = $values;
    }

    public static function none() {
        return new Option(array());
    }

    public static function some($value) {
        return new Option(array($value));
    }

    public function map($function) {
        if ($this->is_empty()) {
            return $this;
        }

        return self::some(
            call_user_func($function, $this->values[0])
        );
    }

    public function get_or_else($fallback) {
        return $this->is_empty()
            ? call_user_func($fallback)
            : $this->values[0];
    }

    public function filter($predicate) {
        if ($this->is_empty()) {
            return $this;
        }

        return call_user_func($predicate, $this->values[0])
            ? $this
            : self::none();
    }

    public function values() {
        return $this->values;
    }

    private function is_empty() {
        return count($this->values) === 0;
    }
}
