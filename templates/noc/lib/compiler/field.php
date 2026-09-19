<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/part-compiler.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';
require_once __DIR__ . '/formatter-val.php';
require_once __DIR__ . '/runtime-val.php';

class Field implements Compilable, RuntimeVal {
    use PartCompiler;

    private $label;
    private $value;

    private static function partClasses() {
        return array(
            'label' => StrVal::class,
            'value' => FormatterVal::class
        );
    }

    public static function compile($definition, $schema, $path) {
        if (runtime_type($definition) !== 'object') {
            return CompilationResult::failure(
                array("$path: must be an object")
            );
        }

        $validationResult = check_allowed_keys(
            $definition,
            self::partClasses(),
            $path
        );

        if (!$validationResult->isSuccess()) {
            return $validationResult;
        }

        $compiledPartsResult = Field::compile_parts(
            $definition,
            $schema,
            $path
        );

        if (!$compiledPartsResult->isSuccess()) {
            return $compiledPartsResult;
        }

        $compiledParts = $compiledPartsResult->value();

        return CompilationResult::success(
            new Field(
                $compiledParts['label']->value(),
                $compiledParts['value']->value()
            )
        );
    }

    public function __construct($label, $value) {
        $this->label = $label;
        $this->value = $value;
    }

    public function label() {
        return $this->label;
    }

    public function value() {
        return $this->value;
    }

    public function field() {
        return $this->value()->field();
    }

    public function value_type() {
        return $this->value->field()->value_type();
    }

    public function render($heartbeat) {
        return $this->value->render($heartbeat);
    }
}