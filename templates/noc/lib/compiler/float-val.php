<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/runtime-val.php';

class FloatVal implements Compilable, RuntimeVal {
    private $value;

    public static function compile($definition, $schema, $path) {
        if (!is_float($definition)) {
            return CompilationResult::failure(array("$path: must be a float"));
        }

        return CompilationResult::success(new FloatVal($definition));
    }

    public function __construct($value) {
        $this->value = $value;
    }

    public function value() {
        return $this->value;
    }

    public function value_type() {
        return 'float';
    }

    public function render($heartbeat) {
        return $this->value();
    }
}