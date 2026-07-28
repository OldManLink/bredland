<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/part-compiler.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';
require_once __DIR__ . '/field.php';

class FieldList implements Compilable {
    private $fields = array();
    public static function compile($definitions, $schema, $path) {
        $fields = array();
        $errors = array();

        if (!is_array($definitions)) {
            return CompilationResult::failure(
                array("$path: must be an array")
            );
        }

        foreach ($definitions as $index => $definition) {
            $fieldPath = indexed_path($path, $index);
            $result = Field::compile($definition, $schema, $fieldPath);

            if ($result->isSuccess()) {
                $field = $result->value();
                $fieldName = $field->field()->value();

                if (array_key_exists($fieldName, $fields)) {
                    $errors[] = "$fieldPath.field: duplicate field: $fieldName";
                } else {
                    $fields[$fieldName] = $field;
                }
            } else {
                $errors = array_merge($errors, $result->errors());
            }
        }

        if (count($errors) > 0) {
            return CompilationResult::failure($errors);
        }

        return CompilationResult::success(new FieldList($fields));
    }

    public function __construct($fields) {
        $this->fields = $fields;
    }

    public function fields() {
        return $this->fields;
    }

    public function get($name) {
        if (!array_key_exists($name, $this->fields)) {
            throw new InvalidArgumentException('Field not found: ' . $name);
        }

        return $this->fields[$name];
    }
}