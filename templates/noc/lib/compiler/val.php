<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/bool-val.php';
require_once __DIR__ . '/int-val.php';
require_once __DIR__ . '/float-val.php';
require_once __DIR__ . '/str-val.php';

class Val implements Compilable {
    private $value;

    private static function valueClasses() {
        return array(
            'boolean' => BoolVal::class,
            'integer' => IntVal::class,
            'float' => FloatVal::class,
            'string' => StrVal::class,
        );
    }

    public static function compile($definition, $schema, $path) {
        if (is_null($definition)) {
            return CompilationResult::failure(array("$path: must not be undefined"));
        }
        $valueType = runtime_type($definition);
        if (!isset(Val::valueClasses()[$valueType])) {
            return CompilationResult::failure(array("$path: unsupported value_type: $valueType"));
        }

        $valueClass = Val::valueClasses()[$valueType];
        return call_user_func(
            array($valueClass, 'compile'),
                $definition,
                $schema,
                "$path.$partName"
        );
    }

    public function __construct($value) {
        $this->value = $value;
    }

    public function value() {
        return $this->value;
    }
}