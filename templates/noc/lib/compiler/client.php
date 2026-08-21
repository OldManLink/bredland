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
require_once dirname(__DIR__) . '/noc.php';
require_once dirname(__DIR__) . '/notification.php';

class Client implements Compilable {
    use PartCompiler;
    private $host;
    private $title;
    private $field_list;
    private $rules;
    private $order;
    private $notifications = array();
    private $heartbeat = null;
    private $health = null;

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

    public function __construct($host, $title, $field_list, $rules, $order) {
        $this->host = $host;
        $this->title = $title;
        $this->field_list = $field_list;
        $this->rules = $rules;
        $this->order = $order;
    }

    public function host() {
        return $this->host;
    }

    public function title() {
        return $this->title;
    }

    public function field_list() {
        return $this->field_list;
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

    public function health() {
        if ($this->health !== null) {
            return $this->health;
        }

        if ($this->heartbeat === null) {
            return 'critical';
        }

        return $this->default_health();
    }

    public function setHealth($health) {
        $this->health = $health;
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
        return $this;
    }

    public function get($field_name) {
       if ($this->heartbeat === null) {
           return 'unavailable';
       }
        return $this->field_list
            ->get($field_name)
            ->render($this->heartbeat);
    }

    public function get_order() {
        return $this->order()->value();
    }

    public function get_title() {
        return $this->title()->value();
    }

    public function heartbeat() {
        return $this->heartbeat;
    }

    public function heartbeat_age() {
        if ($this->heartbeat === null) {
            throw new Exception('Programming error: Client has not been rendered');
        }

        return strtotime(Noc::now()) - strtotime($this->heartbeat['ts']);
    }

    private function default_health() {
        $age = $this->heartbeat_age();
        $ttl = $this->heartbeat['ttl'];

        if ($age < 1.2 * $ttl) {
            return 'healthy';
        }

        if ($age < 4 * $ttl) {
            return 'warning';
        }

        return 'critical';
    }

    public function health_colour() {
        $colours = array(
            'healthy' => 'green',
            'warning' => 'yellow',
            'critical' => 'red'
        );

        return $colours[$this->health()];
    }

    function formatted_heartbeat_age() {
        if ($this->heartbeat === null) {
            return 'unavailable';
        }

        return $this->formatted_duration_seconds(
                $this->heartbeat_age()
            ) . ' ago';
    }

    function get_heartbeat() {
        if ($this->heartbeat === null) {
            return 'unavailable';
        }

        return $this->heartbeat();
    }

    function formatted_duration_seconds($seconds) {
        $seconds = (int)$seconds;

        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . 'm ' . $seconds . 's';
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        if ($hours < 24) {
            return $hours . 'h ' . $minutes . 'm';
        }

        $days = floor($hours / 24);
        $hours = $hours % 24;

        return $days . 'd ' . $hours . 'h';
    }
}
