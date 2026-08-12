<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';

class TelemetryDrawer extends HtmlTag {
    public function __construct($indentation_level, $client) {
        $child_level = $indentation_level + 1;
        $grandchild_level = $indentation_level + 2;

        $heartbeat_json = json_encode($client->get_heartbeat());
        $escaped_heartbeat = htmlspecialchars(
            $heartbeat_json,
            ENT_QUOTES,
            'UTF-8'
        );

        $telemetry = new HtmlTag(
            $child_level,
            'pre',
            array(
                new TextRenderable(
                    $grandchild_level,
                    $escaped_heartbeat
                )
            ),
            array('class' => 'telemetry'),
            true
        );

        parent::__construct(
            $indentation_level,
            'template',
            array($telemetry),
            array(
                'id' => htmlspecialchars(
                    $client->host()->value(),
                    ENT_QUOTES,
                    'UTF-8'
                ) . '-telemetry-template'
            )
        );
    }
}
