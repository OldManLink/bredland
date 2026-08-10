<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';
require_once __DIR__ . '/client.php';

class CardHead extends HtmlTag {
    public function __construct($indentation_level, $client) {
        $child_level = $indentation_level + 1;

        $health_colour = $client->health_colour();
        $title = htmlspecialchars(
            $client->get_title(),
            ENT_QUOTES,
            'UTF-8'
        );

        $health_indicator = new HtmlTag(
            $child_level,
            'span',
            array(),
            array( 'class' => 'led ' . $health_colour),
            true
        );

        parent::__construct(
            $indentation_level,
            'h1',
            array(
                $health_indicator,
                new TextRenderable(
                    $child_level,
                    $title
                )
            )
        );
    }
}
