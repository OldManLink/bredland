<?php

require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';

class NotificationPanel extends HtmlTag {
    public function __construct($indentation_level, $client) {
        $child_level = $indentation_level + 1;
        $grandchild_level = $indentation_level + 2;
        $children = array();

        $close_button = new HtmlTag(
            $child_level,
            'button',
            array(
                new TextRenderable(
                    $child_level,
                    '×'
                )
            ),
            array(
                'class' => 'notification-panel-close',
                'type' => 'button'
            ),
            true
        );

        $children[] = $close_button;

        foreach ($client->notifications() as $notification) {
            $children[] = new HtmlTag(
                $child_level,
                'div',
                array(
                    new TextRenderable(
                        $grandchild_level,
                        $notification->text()
                    )
                ),
                array(
                    'class' => 'notification-text'
                )
            );
        }

        parent::__construct(
            $indentation_level,
            'div',
            $children,
            array(
                'id' => htmlspecialchars(
                    $client->host()->value(),
                    ENT_QUOTES,
                    'UTF-8'
                ) . '-notification-panel',
                'class' => 'notification-panel hidden'
            )
        );
    }
}