<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class BoolVal implements Compilable {

    public static function compile($definition, $schema, $path) {
        if (!is_bool($definition)) {
            return CompilationResult::failure(array("$path must be a boolean"));
        }

        return CompilationResult::success($definition);
    }
}