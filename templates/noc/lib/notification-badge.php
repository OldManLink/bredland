<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';

class NotificationBadge extends HtmlTag {
    public function __construct($indentation_level, $count) {
        parent::__construct(
            $indentation_level,
            'span',
            array(
                new TextRenderable(
                    $indentation_level,
                    (string) $count
                )
            ),
            array('class' => 'notification-badge'), true);
    }
}