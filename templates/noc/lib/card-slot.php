<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/card-container.php';
require_once __DIR__ . '/telemetry-drawer.php';

class CardSlot extends HtmlTag {
    public function __construct($indentation_level, $client) {
        $child_level = $indentation_level + 1;
        parent::__construct(
            $indentation_level,
            'div',
            array(
                new CardContainer(
                    $child_level,
                    $client
                ),
                new TelemetryDrawer(
                    $child_level,
                    $client
                )
            ),
            array('class' => 'card-slot')
        );
    }
}
