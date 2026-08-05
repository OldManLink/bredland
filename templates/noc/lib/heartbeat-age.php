<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';
require_once __DIR__ . '/client.php';

class HeartbeatAge extends HtmlTag {
    public function __construct($indentation_level, $client) {
        $age = htmlspecialchars(
            format_heartbeat_age($client['age']),
            ENT_QUOTES,
            'UTF-8'
        );

        parent::__construct(
            $indentation_level,
            'p',
            array(
                new TextRenderable(
                    $indentation_level,
                    'Last heartbeat: ' . $age
                )
            ),
            array(),
            true
        );
    }
}
