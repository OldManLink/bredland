<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class IntVal implements Compilable {

    public static function compile($definition, $schema, $path) {
        if (!is_int($definition)) {
            return CompilationResult::failure(array("$path must be an integer"));
        }

        return CompilationResult::success($definition);
    }
}