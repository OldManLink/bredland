<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/part-compiler.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/field.php';
require_once __DIR__ . '/field-list.php';
require_once __DIR__ . '/rule.php';
require_once __DIR__ . '/int-val.php';
require_once __DIR__ . '/str-val.php';
require_once dirname(__DIR__) . '/notification.php';

class Client implements Compilable {
    use PartCompiler;
    private $host;
    private $title;
    private $fields;
    private $rules;
    private $order;
    private $notifications = array();
    private $heartbeat = null;

    private static function partClasses() {
        return array(
            'host' => StrVal::class,
            'title' => StrVal::class,
            'fields' => FieldList::class,
            'rules' => RuleList::class,
            'order' => IntVal::class,
        );
    }

    /**
     * Returns declarative method contracts only.
     *
     * Maps method names to argument compiler classes.
     * Receivers must not implement compilation logic here.
     */
    public static function compilable_methods() {
        return array(
            'addNotification' => SlotVal::class,
            'setHealth' => HealthVal::class
        );
    }

    public static function compile($definition, $schema, $path) {
        if (!is_array($definition)) {
            return CompilationResult::failure(array("$path: must be an object"));
        }

        $validationResult = check_allowed_keys(
            $definition,
            self::partClasses(),
            $path
        );

        if (!$validationResult->isSuccess()) {
            return $validationResult;
        }

        $compiledPartsResult = Client::compile_parts($definition, $schema, $path);

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

    public function notifications() {
        return $this->notifications;
    }

    public function notification_count() {
        return count($this->notifications);
    }

    public function addNotification($text) {
        $this->notifications[] = new Notification($text);
    }

    public function render($heartbeat) {
        $this->heartbeat = $heartbeat;

        foreach ($this->rules() as $rule) {
            $rule->render($heartbeat, array($this));
        }
    }

    public function get($fieldName) {
       if ($this->heartbeat === null) { throw new Exception('Programming error: Client has not been rendered');};
        return $this->fields
            ->get($fieldName)
            ->render($this->heartbeat);
    }
}
