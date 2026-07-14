<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/field.php';
require_once __DIR__ . '/rule.php';
require_once __DIR__ . '/int-val.php';
require_once __DIR__ . '/str-val.php';

class Client implements Compilable {
    private $host;
    private $title;
    private $fields;
    private $rules;
    private $order;

    private static function partClasses() {
        return array(
            'host' => StrVal::class,
            'title' => StrVal::class,
            'fields' => FieldList::class,
            'rules' => RuleList::class,
            'order' => IntVal::class,
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

        $compiledPartsResult = Client::compileParts($definition, $schema, $path);

        if (!$compiledPartsResult->isSuccess()) {
            return $compiledPartsResult;
        }

        $compiledParts = $compiledPartsResult->value();

        return CompilationResult::success(
            new Client(
                $compiledParts['host']->value(),
                $compiledParts['title']->value(),
                $compiledParts['fields']->value(),
                $compiledParts['rules']->value(),
                $compiledParts['order']->value()
            )
        );
    }

    private static function compileParts($definition, $schema, $path) {
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

    public function __construct($host, $title, $fields, $rules, $order) {
        $this->host = $host;
        $this->title = $title;
        $this->fields = $fields;
        $this->rules = $rules;
        $this->order = $order;
    }

    public function host() {
        return $this->host;
    }

    public function title() {
        return $this->title;
    }

    public function fields() {
        return $this->fields;
    }

    public function rules() {
        return $this->rules;
    }

    public function order() {
        return $this->order;
    }
}
