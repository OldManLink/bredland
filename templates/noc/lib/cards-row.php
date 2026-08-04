<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/card-slot.php';

class CardsRow extends HtmlTag {
    public function __construct($indentation_level, $clients, $template_file) {
        $child_level = $indentation_level + 1;
        $card_slots = array();

        foreach ($clients as $client) {
            $card_slots[] = new CardSlot(
                $child_level,
                $client,
                $template_file
            );
        }

        parent::__construct(
            $indentation_level,
            'div',
            $card_slots,
            array('class' => 'cards-row')
        );
    }
}
