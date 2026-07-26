<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/slot-val.php';
require_once __DIR__ . '/health-val.php';

class MethodVal {
    private $name;
    private $argument_class;

    public static function compile($definition, $methods, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, "$path.method");
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if (!array_key_exists($definition, $methods)) {
            return CompilationResult::failure(array("$path: unsupported method: $definition"));
        }

        if (!is_string($methods[$definition])) {
            return CompilationResult::failure(array("$path: invalid method definition: $definition"));
        }

        return CompilationResult::success(new MethodVal($definition, $methods[$definition]));
    }

    public function __construct($name, $argument_class) {
        $this->name = $name;
        $this->argument_class = $argument_class;
    }

    public function name() {
        return $this->name;
    }

    public function argument_class() {
        return $this->argument_class;
    }
}