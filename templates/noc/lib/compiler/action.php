<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class Action implements Compilable {
    private $method;
    private $argument;

    private static function partKeys() {
        return array(
            'method' => true,
            'argument' => true,
        );
    }

    private static function methods() {
        return array(
            'addNotification' => true,
            'setHealth' => true
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

        $method = $definition['method'];
        if (!is_string($method) || $method === '') {
            return CompilationResult::failure(array("$path.method: must be a non-empty string"));
        }

        if (!isset(Action::methods()[$method])) {
            return CompilationResult::failure(array("$path: unsupported method $method"));
        }

        $argument = $definition['argument'];
        if (!is_string($argument) || $argument === '') {
            return CompilationResult::failure(array("$path.argument: must be a non-empty string"));
        }
        if ($method == 'setHealth' && !isset(Action::healthValues()[$argument])) {
            return CompilationResult::failure(array("$path.$method: unsupported argument $argument"));
        }

        return CompilationResult::success(
            new Action($method, $argument)
        );
    }

    public function __construct($method, $argument) {
        $this->method = $method;
        $this->argument = $argument;
    }

    public function method() {
        return $this->method;
    }

    public function argument() {
        return $this->argument;
    }
}
