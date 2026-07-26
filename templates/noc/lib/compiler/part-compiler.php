<?php
trait PartCompiler {
    private static function compile_parts($definition, $schema, $path) {
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
}