<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class Effect implements Compilable {
    private $receiver;
    private $attribute;
    private $argument;

    private static function partKeys() {
        return array(
            'type' => true,
            'attribute' => true,
            'value' => true,
        );
    }

    private static function receivers() {
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

    public static function compile($definition, $path) {
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

        $receiver = $definition['type'];
        if (!is_string($receiver) || $receiver === '') {
            return CompilationResult::failure(array("$path.type: must be a non-empty string"));
        }

        $attribute = $definition['attribute'];
        if (!isset(Effect::receivers()[$receiver])) {
            return CompilationResult::failure(array("$path: unsupported type $receiver"));
        } elseif (Effect::receivers()[$receiver] !== $attribute) {
            return CompilationResult::failure(array("$path.$receiver: unsupported attribute: $attribute"));
        }

        $argument = $definition['value'];
        if (!is_string($argument) || $argument === '') {
            return CompilationResult::failure(array("$path.$attribute: must be a non-empty string"));
        }
        if ($receiver == 'health' && !isset(Effect::healthValues()[$argument])) {
            return CompilationResult::failure(array("$path.$receiver: unsupported value $argument"));
        }

        return CompilationResult::success(
            new Effect($receiver, $attribute, $argument)
        );
    }

    public function __construct($receiver, $attribute, $argument) {
        $this->receiver = $receiver;
        $this->attribute = $attribute;
        $this->argument = $argument;
    }

    public function receiver() {
        return $this->receiver;
    }

    public function attribute() {
        return $this->attribute;
    }

    public function argument() {
        return $this->argument;
    }
}
