<?php
require_once dirname(__DIR__) . '/compatibility.php';
require_once dirname(__DIR__) . '/option.php';
trait PartCompiler {
    private static function compile_parts($definition, $schema, $path) {
        $partClasses = self::partClasses();
        $optionalParts = self::optionalParts();
        $compiledParts = array();
        $errors = array();

        if (runtime_type($definition) !== 'object') {
            return CompilationResult::failure(
                array("$path: must be an object")
            );
        }

        foreach ($definition as $partName => $partDefinition) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $partName) !== 1) {
                return CompilationResult::failure(
                    array("$path: invalid identifier: $partName")
                );
            }

            if (!isset($partClasses[$partName])) {
                return CompilationResult::failure(
                    array("$path: unsupported attribute: $partName")
                );
            }
        }

        foreach ($partClasses as $partName => $partClass) {
            if (!array_key_exists($partName, $definition)) {
                if (isset($optionalParts[$partName])) {
                    $compiledParts[$partName] =
                        CompilationResult::success(Option::none());
                } else {
                    $errors[] = "$path.$partName: missing required part";
                }

                continue;
            }

            if (!class_exists($partClass)) {
                return CompilationResult::failure(
                    array(
                        "$path.$partName: Compiler class does not exist: $partClass."
                    )
                );
            }

            if (!is_subclass_of($partClass, 'Compilable')) {
                return CompilationResult::failure(
                    array(
                        "$path.$partName: Class $partClass does not implement Compilable."
                    )
                );
            }

            $result = call_user_func(
                array($partClass, 'compile'),
                $definition[$partName],
                $schema,
                "$path.$partName"
            );

            if (!$result->isSuccess()) {
                $errors = array_merge($errors, $result->errors());
                continue;
            }

            $compiledParts[$partName] =
                isset($optionalParts[$partName])
                    ? CompilationResult::success(
                        Option::some($result->value())
                    )
                    : $result;
        }

        return empty($errors)
            ? CompilationResult::success($compiledParts)
            : CompilationResult::failure($errors);
    }

    private static function optionalParts() {
        return array();
    }
}
