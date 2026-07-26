<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';

class HealthVal implements Compilable {
    private $value;
    private static function health_values() {
        return array(
            'healthy' => true,
            'warning' => true,
            'critical' => true
        );
    }

    public static function compile($definition, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, "$path.health");
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if (!isset(self::health_values()[$definition])) {
            return CompilationResult::failure(array("$path: unsupported health value: $definition"));
        }

        return CompilationResult::success(new HealthVal($definition));
    }

    public function __construct($value) {
        $this->value = $value;
    }

    public function value() {
        return $this->value;
    }
}