<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/card.php';

class CardContainer extends HtmlTag {
    private $client;

    public function __construct($indentation_level, $client) {
        $child_level = $indentation_level + 1;
        parent::__construct(
            $indentation_level,
            'div',
            array(new Card($child_level, $client)),
            array('class' => 'card-container')
        );

        $this->client = $client;
    }

}
