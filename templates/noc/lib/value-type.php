<?php
require_once __DIR__ . '/compatibility.php';

class ValueType {
    private static function value_types() {
        return array(
            'boolean' => true,
            'integer' => true,
            'float' => true,
            'string' => true
        );
    }

    public static function is_supported($type) {
        return isset(self::value_types()[$type]);
    }

    public static function matches($type, $value) {
        $runtime_type = runtime_type($value);

        if ($type === 'float') {
            return $runtime_type === 'float'
                || $runtime_type === 'integer';
        }

        return $runtime_type === $type;
    }
}