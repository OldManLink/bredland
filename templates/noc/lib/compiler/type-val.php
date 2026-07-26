<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';

class TypeVal implements Compilable {
    private $value;
    private static function value_types() {
        return array(
            'boolean' => true,
            'integer' => true,
            'float' => true,
            'string' => true
        );
    }

    public static function compile($definition, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, "$path.type");
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if (!isset(self::value_types()[$definition])) {
            return CompilationResult::failure(array("$path: unsupported value_type: $definition"));
        }

        return CompilationResult::success(new TypeVal($definition));
    }

    public function __construct($value) {
        $this->value = $value;
    }

    public function value() {
        return $this->value;
    }
}