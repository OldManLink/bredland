<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';

class DrawerHandle extends HtmlTag {
    public function __construct($indentation_level, $client) {
        $host = htmlspecialchars(
            $client['host'],
            ENT_QUOTES,
            'UTF-8'
        );

        parent::__construct(
            $indentation_level,
            'button',
            array(new TextRenderable($indentation_level, '=')),
            array(
                'class' => 'drawer-handle',
                'type' => 'button',
                'data-telemetry-toggle' => $host
            ),
            true
        );
    }
}
