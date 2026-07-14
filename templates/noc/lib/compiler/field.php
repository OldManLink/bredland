<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';
require_once __DIR__ . '/field-val.php';
require_once __DIR__ . '/type-val.php';
require_once __DIR__ . '/format-val.php';

class Field implements Compilable {
    private $label;
    private $field;
    private $value_type;
    private $format;

    private static function partClasses() {
        return array(
            'label' => StrVal::class,
            'field' => FieldVal::class,
            'value_type' => TypeVal::class,
            'format' => FormatVal::class,
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

        $compiledPartsResult = Field::compileParts($definition, $schema, $path);

        if (!$compiledPartsResult->isSuccess()) {
            return $compiledPartsResult;
        }

        $compiledParts = $compiledPartsResult->value();
        $format = $compiledParts['format']->value();
        $format_name = $format->name();
        $value_type = $compiledParts['value_type']->value();
        if(!isset($format->valueTypes()[$value_type])) {
            return CompilationResult::failure(array("$path.$format_name: incompatible with $value_type"));
        }

        return CompilationResult::success(
            new Field(
                $compiledParts['label']->value(),
                $compiledParts['field']->value(),
                $value_type,
                $format_name
            )
        );
    }

    private static function compileParts($definition, $schema, $path) {
        $partClasses = self::partClasses();
        $compiledParts = array();
        $errors = array();

        foreach ($definition as $partName => $partDefinition) {
            $partClass = $partClasses[$partName];

            if (!class_exists($partClass)) {
                return CompilationResult::failure(array("$path.$partname: Compiler class does not exist: $partClass."));
            }

            if (!is_subclass_of($partClass, 'Compilable')) {
                return CompilationResult::failure(array("$path.$partName: Class $partClass does not implement Compilable."));
            }

            $result = call_user_func(
                array($partClass, 'compile'),
                $partDefinition,
                $schema,
                "$path.$partName"
            );

            $compiledParts[$partName] = $result;
            if(!$result->isSuccess()) {
                $errors = array_merge($errors, $result->errors());
            }
        }
        return empty($errors) ?
            CompilationResult::success($compiledParts) :
            CompilationResult::failure($errors);
    }

    public function __construct($label, $field, $value_type, $format) {
        $this->label = $label;
        $this->field = $field;
        $this->value_type = $value_type;
        $this->format = $format;
    }

    public function label() {
        return $this->label;
    }

    public function field() {
        return $this->field;
    }

    public function value_type() {
        return $this->value_type;
    }

    public function format() {
        return $this->format;
    }
}

class FieldList implements Compilable {
    public static function compile($definitions, $schema, $path) {
        $fields = array();
        $errors = array();

        if (!is_array($definitions)) {
            return CompilationResult::failure(
                array("$path: must be an array")
            );
        }

        foreach ($definitions as $index => $definition) {
                $result = Field::compile($definition, $schema, indexed_path($path, $index));

                if ($result->isSuccess()) {
                    $fields[] = $result->value();
                } else {
                    $errors = array_merge($errors, $result->errors());
                }
            }

            if (count($errors) > 0) {
                return CompilationResult::failure($errors);
            }

            return CompilationResult::success($fields);
    }
}