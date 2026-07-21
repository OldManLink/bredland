<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/str-val.php';
require_once __DIR__ . '/field-val.php';


class SlotVal implements Compilable {
    private $parts;

    public static function compile($definition, $schema, $path) {
        if (!is_string($definition) || $definition === '') {
            return CompilationResult::failure(array("$path: must be a non-empty string"));
        }

        $compiledPartsResult = SlotVal::compile_parts($definition, $schema, $path);

        if (!$compiledPartsResult->isSuccess()) {
            return $compiledPartsResult;
        }

        $compiledParts = $compiledPartsResult->value();

        return CompilationResult::success($compiledParts);
    }

    private static function compile_parts($definition, $schema, $path) {
        $pattern = '/\{\{([A-Za-z_][A-Za-z0-9_]*)\}\}/';
        $matches = array();

        preg_match_all(
            $pattern,
            $definition,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $compiledParts = array();
        $errors = array();
        $position = 0;

        foreach ($matches[0] as $index => $match) {
            $placeholder = $match[0];
            $placeholderPosition = $match[1];

            if ($placeholderPosition > $position) {
                $strResult = StrVal::compile(
                    substr(
                        $definition,
                        $position,
                        $placeholderPosition - $position
                    ),
                    $schema,
                    indexed_path($path, count($compiledParts))
                );

                if ($strResult->isSuccess()) {
                    $compiledParts[] = $strResult->value();
                } else {
                    $errors = array_merge($errors, $strResult->errors());
                }
            }

            $fieldResult = FieldVal::compile(
                $matches[1][$index][0],
                $schema,
                indexed_path($path, count($compiledParts))
            );

            if ($fieldResult->isSuccess()) {
                $compiledParts[] = $fieldResult->value();
            } else {
                $errors = array_merge($errors, $fieldResult->errors());
            }

            $position = $placeholderPosition + strlen($placeholder);
        }

        if ($position < strlen($definition)) {
            $strResult = StrVal::compile(
                substr($definition, $position),
                $schema,
                indexed_path($path, count($compiledParts))
            );

            if ($strResult->isSuccess()) {
                $compiledParts[] = $strResult->value();
            } else {
                $errors = array_merge($errors, $strResult->errors());
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
}