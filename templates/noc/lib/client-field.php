<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';
require_once __DIR__ . '/client.php';

class ClientField extends HtmlTag {
    public function __construct($indentation_level, $client, $field) {
        $label = htmlspecialchars(
            $field['label'],
            ENT_QUOTES,
            'UTF-8'
        );

        $value = htmlspecialchars(
            display_client_field($client, $field),
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
