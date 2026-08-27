<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/runtime-val.php';
require_once __DIR__ . '/slot-val.php';
require_once dirname(__DIR__) . '/notification.php';
require_once dirname(__DIR__) . '/compatibility.php';

class NotificationVal implements Compilable, RuntimeVal {
    private $text;
    private $resolution;

    public static function compile($definition, $schema, $path) {
        if (runtime_type($definition) === 'array') {
            if (count($definition) !== 2) {
                return CompilationResult::failure(
                    array("$path: must be a string or a two-element array")
                );
            }

            $textResult = SlotVal::compile(
                $definition[0],
                $schema,
                indexed_path($path, 0)
            );

            if (!$textResult->isSuccess()) {
                return $textResult;
            }

            $resolutionResult = StrVal::compile(
                $definition[1],
                $schema,
                indexed_path($path, 1)
            );

            if (!$resolutionResult->isSuccess()) {
                return $resolutionResult;
            }

            return CompilationResult::success(
                new NotificationVal(
                    $textResult->value(),
                    $resolutionResult->value()
                )
            );
        }

        $textResult = SlotVal::compile(
            $definition,
            $schema,
            $path
        );

        if (!$textResult->isSuccess()) {
            return $textResult;
        }

        return CompilationResult::success(
            new NotificationVal($textResult->value())
        );
    }

    public function __construct($text, $resolution = null) {
        $this->text = $text;
        $this->resolution = $resolution;
    }

    private function has_resolution() {
        return $this->resolution !== null;
    }

    public function render($heartbeat) {
        if ($this->has_resolution()) {
            return new Notification(
                $this->text->render($heartbeat),
                $this->resolution->render($heartbeat)
            );
        }

        return new Notification(
            $this->text->render($heartbeat)
        );
    }
}