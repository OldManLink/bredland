<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class Val implements Compilable {
    private $value;
    private $valueType;

    public static function compile($definition, $schema, $path) {
        if (is_null($definition)) {
            return CompilationResult::failure(array("$path: must not be undefined"));
        }

        return CompilationResult::success(
            new Val(
                $definition,
                gettype($definition)
            )
        );
    }

    public function __construct($value, $valueType) {
        $this->value = $value;
        $this->valueType = $valueType;
    }

    public function value() {
        return $this->value;
    }

    public function valueType() {
        return $this->valueType;
    }
}