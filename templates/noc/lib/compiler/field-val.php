<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/slot-part.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';

class FieldVal implements Compilable, SlotPart {
    private $value;
    private $value_type;

    public static function compile($definition, $schema, $path) {
        if (is_array($definition)) {
            if(count($definition) === 1 && isset($definition['field'])) {
                $definition = $definition['field'];
            }
            else {
                $json = json_encode($definition);
                return CompilationResult::failure(array("$path: invalid definition: $json"));
            }
        }

        $strValResult = StrVal::compile($definition, $schema, $path);
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if(!isset($schema[$definition])) {
            return CompilationResult::failure(array("$path: '$definition' must exist in schema"));
        }

        return CompilationResult::success(new FieldVal($definition, $schema[$definition]['value_type']));
    }

    public function __construct($value, $value_type) {
        $this->value = $value;
        $this->value_type = $value_type;
    }

    public function value() {
        return $this->value;
    }

    public function value_type() {
        return $this->value_type;
    }

    public function render($heartbeat) {
        return array_key_exists($this->value(), $heartbeat)
            ? $heartbeat[$this->value()]
            : 'unavailable';
    }
}