<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/receiver-val.php';
require_once __DIR__ . '/method-val.php';

class Action implements Compilable {
    private $receiver;
    private $method;
    private $argument;

    private static function partKeys() {
        return array(
            'receiver' => true,
            'method' => true,
            'argument' => true,
        );
    }

    public static function compile($definition, $schema, $path) {
        if (!is_array($definition)) {
            return CompilationResult::failure(array("$path must be an object"));
        }

        $validationResult = check_allowed_keys($definition, self::partKeys(), $path);
        if (!$validationResult->isSuccess()) {
            return $validationResult;
        }

        $receiverResult = ReceiverVal::compile($definition['receiver'], $schema, "$path.receiver");
        if (!$receiverResult->isSuccess()) {
            return $receiverResult;
        }

        $methods = $receiverResult->value()->compilable_methods();
        $methodResult = MethodVal::compile( $definition['method'], $methods, $schema, "$path.method");
        if (!$methodResult->isSuccess()) {
            return $methodResult;
        }

        $argumentClass = $methodResult->value()->argument_class();
        $argumentResult = $argumentClass::compile($definition['argument'], $schema, "$path.argument");
        if (!$argumentResult->isSuccess()) {
            return $argumentResult;
        }

        return CompilationResult::success(
            new Action($receiverResult->value(), $methodResult->value(), $argumentResult->value())
        );
    }

    public function __construct($receiver, $method, $argument) {
        $this->receiver = $receiver;
        $this->method = $method;
        $this->argument = $argument;
    }

    public function receiver() {
        return $this->receiver;
    }

    public function method() {
        return $this->method;
    }

    public function argument() {
        return $this->argument;
    }
}
