<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class OpVal implements Compilable {
    private $name;
    private $operandTypes;

    private static function supportedOperands() {
        return array(
            'equals' => array(
                'boolean' => true,
                'integer' => true,
                'double' => true,
                'string' => true
            ),
            'lessThan' => array(
                'integer' => true,
                'double' => true
            )
        );
    }

    public static function compile($definition, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, "$path.operator");
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if(!isset(OpVal::supportedOperands()[$definition])) {
            return CompilationResult::failure(array("$path: unsupported operator: $definition"));
        }

        return CompilationResult::success(
            new OpVal(
                $definition,
                OpVal::supportedOperands()[$definition]
            )
        );
    }

    public function __construct($name, $operandTypes) {
        $this->name = $name;
        $this->operandTypes = $operandTypes;
    }

    public function name() {
        return $this->name;
    }

    public function operandTypes() {
        return $this->operandTypes;
    }
}