<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/card-head.php';
require_once __DIR__ . '/heartbeat-age.php';
require_once __DIR__ . '/client-field-list.php';
require_once __DIR__ . '/drawer-handle.php';
require_once __DIR__ . '/client.php';

class Card extends HtmlTag {
    public function __construct($indentation_level, $client) {
        $child_level = $indentation_level + 1;

        $health_colour = $client->health_colour();

        parent::__construct(
            $indentation_level,
            'div',
            array(
                new CardHead(
                    $child_level,
                    $client
                ),
                new HeartbeatAge(
                    $child_level,
                    $client
                ),
                new ClientFieldList(
                    $child_level,
                    $client
                ),
                new DrawerHandle(
                    $child_level,
                    $client
                )
            ),
            array(
                'class' => 'card ' . $health_colour
            )
        );
    }
}
