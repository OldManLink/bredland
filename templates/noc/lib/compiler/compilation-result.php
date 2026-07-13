<?php

class CompilationResult {
    private $value;
    private $errors;

    private function __construct($value, $errors) {
        $this->value = $value;
        $this->errors = $errors;
    }

    public static function success($value) {
        return new self($value, array());
    }

    public static function failure($errorMessages) {
        return new self(null, $errorMessages);
    }

    public function isSuccess() {
        return count($this->errors) === 0;
    }

    public function value() {
        return $this->value;
    }

    public function errors() {
        return $this->errors;
    }
}