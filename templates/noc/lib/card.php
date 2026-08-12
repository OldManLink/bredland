<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/card-head.php';
require_once __DIR__ . '/heartbeat-age.php';
require_once __DIR__ . '/client-field-list.php';
require_once __DIR__ . '/drawer-handle.php';
require_once __DIR__ . '/notification-panel.php';

class Card extends HtmlTag {
    public function __construct($indentation_level, $client) {
        $child_level = $indentation_level + 1;

        $health_colour = $client->health_colour();

        $children = array(
            new CardHead(
                $child_level,
                $client
            )
        );

        if ($client->notification_count() > 0) {
            $children[] = new NotificationPanel(
                $child_level,
                $client
            );
        }

        $children[] = new HeartbeatAge(
            $child_level,
            $client
        );

        $children[] = new ClientFieldList(
            $child_level,
            $client
        );

        $children[] = new DrawerHandle(
            $child_level,
            $client
        );

        parent::__construct(
            $indentation_level,
            'div',
            $children,
            array(
                'class' => 'card ' . $health_colour
            )
        );
    }
}
