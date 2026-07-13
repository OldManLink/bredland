<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class Predicate implements Compilable {
    private $receiver;
    private $operator;
    private $argument;

    private static function partKeys() {
        return array(
            'field' => true,
            'operator' => true,
            'value' => true,
        );
    }

    private static function operators() {
        return array(
            'equals' => true,
            'lessThan' => true
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

        $receiver = $definition['field'];
        if (!is_string($receiver) || $receiver === '') {
            return CompilationResult::failure(array("$path: field must be a non-empty string"));
        }

        $operator = $definition['operator'];
        if (!isSet(Predicate::operators()[$operator])) {
            return CompilationResult::failure(array("$path: unsupported operator: $operator"));
        }

        $argument = $definition['value'];
        if (
            $operator === 'equals' &&
            !is_bool($argument) &&
            !is_int($argument) &&
            !is_float($argument) &&
            !is_string($argument)
        ) {
            return CompilationResult::failure(array("$path.equals: must be a scalar value"));
        }

        if (
            $operator === 'lessThan' &&
            !is_int($argument) &&
            !is_float($argument)
        ) {
            return CompilationResult::failure(array("$path.lessThan: must be numeric"));
        }

        return CompilationResult::success(
            new Predicate($receiver, $operator, $argument)
        );
    }

    public function __construct($receiver, $operator, $argument) {
        $this->receiver = $receiver;
        $this->operator = $operator;
        $this->argument = $argument;
    }

    public function receiver() {
        return $this->receiver;
    }

    public function operator() {
        return $this->operator;
    }

    public function argument() {
        return $this->argument;
    }
}

