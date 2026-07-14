<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class Effect implements Compilable {
    private $type;
    private $attribute;
    private $argument;

    private static function partKeys() {
        return array(
            'type' => true,
            'attribute' => true,
            'value' => true,
        );
    }

    private static function types() {
        return array(
            'notification' => 'message',
            'health' => 'value'
        );
    }

    private static function healthValues() {
        return array(
            'healthy' => true,
            'warning' => true,
            'critical' => true
        );
    }

    public static function compile($definition, $schema, $path) {
        if (!is_array($definition)) {
            return CompilationResult::failure(array("$path must be an object"));
        }

        $validationResult = check_allowed_keys(
            $definition,
            self::partKeys(),
            $path
        );

        if (!$validationResult->isSuccess()) {
            return $validationResult;
        }

        $type = $definition['type'];
        if (!is_string($type) || $type === '') {
            return CompilationResult::failure(array("$path.type: must be a non-empty string"));
        }

        $attribute = $definition['attribute'];
        if (!isset(Effect::types()[$type])) {
            return CompilationResult::failure(array("$path: unsupported type $type"));
        } elseif (Effect::types()[$type] !== $attribute) {
            return CompilationResult::failure(array("$path.$type: unsupported attribute: $attribute"));
        }

        $argument = $definition['value'];
        if (!is_string($argument) || $argument === '') {
            return CompilationResult::failure(array("$path.$attribute: must be a non-empty string"));
        }
        if ($type == 'health' && !isset(Effect::healthValues()[$argument])) {
            return CompilationResult::failure(array("$path.$type: unsupported value $argument"));
        }

        return CompilationResult::success(
            new Effect($type, $attribute, $argument)
        );
    }

    public function __construct($type, $attribute, $argument) {
        $this->type = $type;
        $this->attribute = $attribute;
        $this->argument = $argument;
    }

    public function type() {
        return $this->type;
    }

    public function attribute() {
        return $this->attribute;
    }

    public function argument() {
        return $this->argument;
    }
}
