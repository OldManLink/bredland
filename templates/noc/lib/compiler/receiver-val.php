<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/utils.php';
require_once dirname(__DIR__) . '/noc.php';
require_once __DIR__ . '/client.php';

class ReceiverVal implements Compilable {
    private $name;
    private $receiver_class;

    private static function receivers() {
        return array(
            'client' => Client::class,
            'noc' => Noc::class
        );
    }

    public static function compile($definition, $schema, $path) {
        $strValResult = StrVal::compile($definition, $schema, "$path.receiver");
        if (!$strValResult->isSuccess()) {
            return $strValResult;
        }

        if (!isset(self::receivers()[$definition])) {
            return CompilationResult::failure(array("$path: unsupported receiver: $definition"));
        }

        return CompilationResult::success(new ReceiverVal($definition, self::receivers()[$definition]));
    }

    public function __construct($name, $receiver_class) {
        $this->name = $name;
        $this->receiver_class = $receiver_class;
    }

    public function name() {
        return $this->name;
    }

    public function receiver_class() {
        return $this->receiver_class;
    }

    public function compilable_methods() {
        $class = $this->receiver_class;
        return $class::compilable_methods();
    }

    public function render($receivers) {
        return array_filter($receivers, function ($r) {
            return $r instanceof $this->receiver_class;
        });
    }
}