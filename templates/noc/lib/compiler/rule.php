<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/part-compiler.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/predicate.php';
require_once __DIR__ . '/action.php';

class Rule implements Compilable {
    use PartCompiler;
    private $predicate;
    private $action;

    private static function partClasses() {
        return array(
            'when' => Predicate::class,
            'then' => Action::class,
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

        $compiledPartsResult = Rule::compile_parts($definition, $schema, $path);

        if (!$compiledPartsResult->isSuccess()) {
            return $compiledPartsResult;
        }

        $compiledParts = $compiledPartsResult->value();

        return CompilationResult::success(
            new Rule(
                $compiledParts['when']->value(),
                $compiledParts['then']->value()
            )
        );
    }

    public function __construct($predicate, $action) {
        $this->predicate = $predicate;
        $this->action = $action;
    }

    public function predicate() {
        return $this->predicate;
    }

    public function action() {
        return $this->action;
    }
}

class RuleList implements Compilable {
    public static function compile($definitions, $schema, $path) {
        $rules = array();
        $errors = array();

        if (!is_array($definitions)) {
            return CompilationResult::failure(
                array("$path: must be an array")
            );
        }

        foreach ($definitions as $index => $definition) {
                $result = Rule::compile($definition, $schema, indexed_path($path, $index));

                if ($result->isSuccess()) {
                    $rules[] = $result->value();
                } else {
                    $errors = array_merge($errors, $result->errors());
                }
            }

            if (count($errors) > 0) {
                return CompilationResult::failure($errors);
            }

            return CompilationResult::success($rules);
    }
}

