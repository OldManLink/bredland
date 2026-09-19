<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/runtime-val.php';
require_once __DIR__ . '/part-compiler.php';
require_once __DIR__ . '/field-val.php';
require_once __DIR__ . '/format-val.php';
require_once dirname(__DIR__) . '/value-type.php';

class FormatterVal implements Compilable, RuntimeVal {
    use PartCompiler;

    private $field;
    private $formatter;

    private static function partClasses() {
        return array(
            'field' => FieldVal::class,
            'formatter' => FormatVal::class
        );
    }

    private static function optionalParts() {
        return array(
            'formatter' => true
        );
    }

    public static function compile($definition, $schema, $path) {
    $partsResult = self::compile_parts(
        $definition,
        $schema,
        $path
    );

    if (!$partsResult->isSuccess()) {
        return $partsResult;
    }

    $field = $partsResult->value()['field']->value();
    $formatter = $partsResult->value()['formatter']->value();

    $compatibilityResult = $formatter
        ->map(function ($format) use ($field, $path) {
            $valueType = $field->value_type();

            return isset($format->value_types()[$valueType])
                ? CompilationResult::success(true)
                : CompilationResult::failure(
                    array(
                        "$path.{$format->name()}: incompatible with $valueType"
                    )
                );
        })
        ->get_or_else(function () {
            return CompilationResult::success(true);
        });

    return $compatibilityResult->isSuccess()
        ? CompilationResult::success(
            new FormatterVal($field, $formatter)
        )
        : $compatibilityResult;
}

    public function __construct($field, $formatter) {
        $this->field = $field;
        $this->formatter = $formatter;
    }

    public function field() {
        return $this->field;
    }

    public function formatter() {
        return $this->formatter;
    }

    public function value_type() {
        return $this->field->value_type();
    }

    public function render($heartbeat) {
        $value = $this->field->render($heartbeat);

        return $this->formatter
            ->map(function ($format) use ($value) {
                $formatter = $format->render();

                return ValueType::matches($this->field->value_type(), $value)
                    ? $formatter($value)
                    : 'unavailable';
            })
            ->get_or_else(function () use ($value) {
                return $value;
            });
    }
}
