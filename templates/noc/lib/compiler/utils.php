<?php
/**
 * Builds a diagnostic path for indexed language elements.
 *
 * This function should not exist.
 *
 * IntelliJ IDEA 2026.1.4 incorrectly matches braces when PHP string literals
 * contain indexed paths such as "rules[$index]". The editor highlights the
 * closing brace of the surrounding function incorrectly, making subsequent
 * code appear unreachable.
 *
 * Constructing the path here avoids the issue while preserving readable
 * diagnostics such as "rules[3]".
 *
 * Future maintainer: if JetBrains ever fixes this, please delete this helper
 * with our blessing.
 */

function indexed_path($base, $index) {
    return $base . '[' . $index . ']';
}

function check_allowed_keys($definition, $allowedKeys, $path) {

    foreach ($allowedKeys as $key => $class) {
        if(!array_key_exists($key, $definition)) {
            return CompilationResult::failure(array("$path: expected $key"));
        }
    }

    foreach ($definition as $key => $value) {
        if (!isset($allowedKeys[$key])) {
            return CompilationResult::failure(array("$path: unsupported attribute $key"));
        }
    }

    return CompilationResult::success(null);
}