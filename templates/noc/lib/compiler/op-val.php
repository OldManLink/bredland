<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';
require_once __DIR__ . '/renderable.php';

class OpVal implements Compilable, Renderable {
    private $name;
    private $operand_types;

    private static function supportedOperands() {
        return array(
            'equals' => array(
                'boolean' => true,
                'integer' => true,
                'float' => true,
                'string' => true
            ),
            'lessThan' => array(
                'integer' => true,
                'float' => true
            )
        );
    }

    public static function compile($definition, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, "$path.operator");
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if(!isset(self::supportedOperands()[$definition])) {
            return CompilationResult::failure(array("$path: unsupported operator: $definition"));
        }

        return CompilationResult::success(
            new OpVal(
                $definition,
                self::supportedOperands()[$definition]
            )
        );
    }

    public function __construct($name, $operand_types) {
        $this->name = $name;
        $this->operand_types = $operand_types;
    }

    public function name() {
        return $this->name;
    }

    public function operand_types() {
        return $this->operand_types;
    }

    public function render() {
        switch ($this->name()) {
            case 'equals':
                return function ($left, $right) {
                    return $left === $right;
                };

            case 'lessThan':
                return function ($left, $right) {
                    if (gettype($left) !== gettype($right)) {
                        throw new Exception(
                            'lessThan requires operands of the same type'
                        );
                    }

                    return $left < $right;
                };
        }
    }
}