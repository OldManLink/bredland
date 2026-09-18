<?php
require_once __DIR__ . '/compilable.php';
require_once __DIR__ . '/compilation-result.php';
require_once __DIR__ . '/runtime-val.php';
require_once __DIR__ . '/slot-val.php';
require_once __DIR__ . '/part-compiler.php';
require_once dirname(__DIR__) . '/notification.php';
require_once dirname(__DIR__) . '/compatibility.php';


class NotificationVal implements Compilable, RuntimeVal {
    use PartCompiler;

    private $text;
    private $resolution;

    private static function partClasses() {
        return array(
            'text' => SlotVal::class,
            'resolution' => StrVal::class
        );
    }

    private static function optionalParts() {
        return array(
            'resolution' => true
        );
    }

    public static function compile($definition, $schema, $path) {
        $partsResult = self::compile_parts(
            $definition,
            $schema,
            $path
        );

        return $partsResult->isSuccess()
            ? CompilationResult::success(
                new NotificationVal(
                    $partsResult->value()['text']->value(),
                    $partsResult->value()['resolution']->value()
                )
            )
            : $partsResult;
    }

    public function __construct($text, $resolution) {
        $this->text = $text;
        $this->resolution = $resolution;
    }

    public function render($heartbeat) {
        $text = $this->text->render($heartbeat);

        return $this->resolution
            ->map(function ($resolution) use ($heartbeat, $text) {
                return new Notification(
                    $text,
                    $resolution->render($heartbeat)
                );
            })
            ->get_or_else(function () use ($text) {
                return new Notification($text);
            });
    }
}
