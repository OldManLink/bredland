<?php
require_once dirname(__DIR__) . '/exports.php';
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';

class FormatVal implements Compilable {
    private $name;
    private $value_types;


    public static function compile($definition, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, "$path-function");
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        $formatters = get_exports()['formatters'];
        if(!isset($formatters[$definition])) {
            return CompilationResult::failure(array("$path: $definition must exist in exports"));
        }
        return CompilationResult::success(
            new FormatVal(
                $definition,
                $formatters[$definition]['value_types']
            )
        );
    }

    public function __construct($name, $value_types) {
        $this->name = $name;
        $this->value_types = $value_types;
    }

    public function name() {
        return $this->name;
    }

    public function value_types() {
        return $this->value_types;
    }
}