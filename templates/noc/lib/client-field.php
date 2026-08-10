<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';
require_once __DIR__ . '/compiler/client.php';

class ClientField extends HtmlTag {
    public function __construct($indentation_level, $client, $field) {
        $label = htmlspecialchars(
            $field->label()->value(),
            ENT_QUOTES,
            'UTF-8'
        );

        $value = htmlspecialchars(
            $client->get($field->field()->value()),
            ENT_QUOTES,
            'UTF-8'
        );

        parent::__construct(
            $indentation_level,
            'p',
            array(
                new TextRenderable(
                    $indentation_level,
                    $label . ': ' . $value
                )
            ),
            array(),
            true
        );
    }
}
