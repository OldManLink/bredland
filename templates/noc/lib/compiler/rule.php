<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';

class Rule implements Compilable {
    private $predicate;
    private $effect;

    private static function partClasses() {
        return array(
            'when' => Predicate::class,
            'then' => Effect::class,
        );
    }

    private static function compileParts($definition, $path) {
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

    public static function compile($definition, $path) {
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

        $compiledPartsResult = Rule::compileParts($definition, $path);

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

    public function __construct($predicate, $effect) {
        $this->predicate = $predicate;
        $this->effect = $effect;
    }

    public function predicate() {
        return $this->predicate;
    }

    public function effect() {
        return $this->effect;
    }
}

class RuleList implements Compilable {
    public static function compile($definitions, $path) {
        $rules = array();
        $errors = array();

        if (!is_array($definitions)) {
            return CompilationResult::failure(
                array("$path: must be an array")
            );
        }

        foreach ($definitions as $index => $definition) {
                $result = Rule::compile($definition, indexed_path($path, $index));

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

