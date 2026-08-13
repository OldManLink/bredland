<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/part-compiler.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';
require_once __DIR__ . '/field-val.php';
require_once __DIR__ . '/format-val.php';
require_once __DIR__ . '/runtime-val.php';
require_once dirname(__DIR__) . '/value-type.php';
require_once dirname(__DIR__) . '/formatters.php';

class Field implements Compilable, RuntimeVal {
    use PartCompiler;

    private $label;
    private $field;
    private $format;

    private static function partClasses() {
        return array(
            'label' => StrVal::class,
            'field' => FieldVal::class,
            'format' => FormatVal::class,
        );
    }

    public static function compile($definition, $schema, $path) {
        if (!is_array($definition)) {
            return CompilationResult::failure(array("$path: must be an object"));
        }

        $validationResult = check_allowed_keys(
            $definition,
            self::partClasses(),
            $path
        );

        if (!$validationResult->isSuccess()) {
            return $validationResult;
        }

        $compiledPartsResult = Field::compile_parts($definition, $schema, $path);

        if (!$compiledPartsResult->isSuccess()) {
            return $compiledPartsResult;
        }

        $compiledParts = $compiledPartsResult->value();

        $field = $compiledParts['field']->value();
        $format = $compiledParts['format']->value();
        $valueType = $field->value_type();

        if (!isset($format->value_types()[$valueType])) {
            return CompilationResult::failure(
                array(
                    "$path.{$format->name()}: incompatible with $valueType"
                )
            );
        }

        return CompilationResult::success(
            new Field(
                $compiledParts['label']->value(),
                $field,
                $format
            )
        );
    }

    public function __construct($label, $field, $format) {
        $this->label = $label;
        $this->field = $field;
        $this->format = $format;
    }

    public function label() {
        return $this->label;
    }

    public function field() {
        return $this->field;
    }

    public function value_type() {
        return $this->field->value_type();
    }

    public function format() {
        return $this->format;
    }

    public function render($heartbeat) {
        $value = $this->field->render($heartbeat);
        $formatter = $this->format->render();

        return ValueType::matches($this->value_type(), $value)
            ? $formatter($value)
            : 'unavailable';
    }
}