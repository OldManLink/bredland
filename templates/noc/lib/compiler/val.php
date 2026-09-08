<?php
require_once dirname(__DIR__) . '/compatibility.php';
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/instantiation-exception.php';
require_once __DIR__ . '/bool-val.php';
require_once __DIR__ . '/int-val.php';
require_once __DIR__ . '/float-val.php';
require_once __DIR__ . '/str-val.php';
require_once __DIR__ . '/field-val.php';

class Val implements Compilable {
    private static function valueClasses() {
        return array(
            'boolean' => BoolVal::class,
            'integer' => IntVal::class,
            'float' => FloatVal::class,
            'string' => StrVal::class,
            'object' => FieldVal::class,
        );
    }

    public static function compile($definition, $schema, $path) {
        if (is_null($definition)) {
            return CompilationResult::failure(array("$path: must not be undefined"));
        }
        $valueType = runtime_type($definition);
        if (!isset(self::valueClasses()[$valueType])) {
            return CompilationResult::failure(array("$path: unsupported value_type: $valueType"));
        }

        $valueClass = self::valueClasses()[$valueType];
        return call_user_func(
            array($valueClass, 'compile'),
                $definition,
                $schema,
                "$path.$valueClass"
        );
    }

    public function __construct($value) {
        throw new InstantiationException("Programming error: Val cannot be instantiated: $value");
    }
}
