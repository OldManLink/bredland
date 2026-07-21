<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/slot-part.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';

class FieldVal implements Compilable, SlotPart {
    private $value;

    public static function compile($definition, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, $path);
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if(!isset($schema[$definition])) {
            return CompilationResult::failure(array("$path: '$definition' must exist in schema"));
        }

        return CompilationResult::success(new FieldVal($definition));
    }

    public function __construct($value) {
        $this->value = $value;
    }

    public function value() {
        return $this->value;
    }
}