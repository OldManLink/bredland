<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/val.php';
require_once __DIR__ . '/runtime-val.php';

class SlotVal implements Compilable, RuntimeVal {
    private $parts;

    public static function compile($definition, $schema, $path) {
        if (runtime_type($definition) !== 'array') {
            return CompilationResult::failure(
                array("$path: must be an array")
            );
        }

        $compiledParts = array();
        $errors = array();

        foreach ($definition as $index => $part) {
            $result = Val::compile(
                $part,
                $schema,
                indexed_path($path, $index)
            );

            if ($result->isSuccess()) {
                $compiledParts[] = $result->value();
            } else {
                $errors = array_merge($errors, $result->errors());
            }
        }

        return empty($errors)
            ? CompilationResult::success(new SlotVal($compiledParts))
            : CompilationResult::failure($errors);
    }

    public function __construct($parts) {
        $this->parts = $parts;
    }

    public function parts() {
        return $this->parts;
    }

    public function render($heartbeat) {
        $result = '';

        foreach ($this->parts as $part) {
            $result .= $part->render($heartbeat);
        }

        return $result;
    }
}