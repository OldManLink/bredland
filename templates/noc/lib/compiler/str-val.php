<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class StrVal implements Compilable {

    public static function compile($definition, $schema, $path) {
        if (!is_string($definition) || $definition === '') {
            return CompilationResult::failure(array("$path: must be a non-empty string"));
        }

        return CompilationResult::success($definition);
    }
}