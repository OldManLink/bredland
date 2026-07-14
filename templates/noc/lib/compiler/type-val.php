<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';

class TypeVal implements Compilable {
    private static function value_types() {
        return array(
            'integer' => true,
            'string' => true,
            'boolean' => true
        );
    }

    public static function compile($definition, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, "$path-type");
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if (!isset(TypeVal::value_types()[$definition])) {
            return CompilationResult::failure(array("$path: unsupported valueType: $definition"));
        }

        return CompilationResult::success($definition);
    }
}