<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/part-compiler.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/field-val.php';
require_once __DIR__ . '/op-val.php';
require_once __DIR__ . '/val.php';

class Predicate implements Compilable {
    use PartCompiler;
    private $receiver;
    private $operator;
    private $argument;

    private static function partClasses() {
        return array(
            'field' => FieldVal::class,
            'operator' => OpVal::class,
            'value' => Val::class
        );
    }

    public static function compile($definition, $schema, $path) {
        if (!is_array($definition)) {
            return CompilationResult::failure(array("$path must be an object"));
        }

        $validationResult = check_allowed_keys(
            $definition,
            self::partClasses(),
            $path
        );

        if (!$validationResult->isSuccess()) {
            return $validationResult;
        }

        $compiledPartsResult = Predicate::compile_parts($definition, $schema, $path);

        if (!$compiledPartsResult->isSuccess()) {
            return $compiledPartsResult;
        }

        $compiledParts = $compiledPartsResult->value();

        $field = $compiledParts['field']->value();
        $operator = $compiledParts['operator']->value();
        $value = $compiledParts['value']->value();
        $operator_name = $operator->name();
        $field_type = $schema[$field->value()]['valueType'];

        if(!isset($operator->operandTypes()[$field_type])) {
            return CompilationResult::failure(array("$path.$operator_name: incompatible with $field_type"));
        }
        $valueValueType = $value->valueType();
        if($value->valueType() !== $field_type) {
            return CompilationResult::failure(array("$path.$operator_name: $valueValueType incompatible with $field_type"));
        }

        return CompilationResult::success(
            new Predicate($field, $operator, $value)
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

