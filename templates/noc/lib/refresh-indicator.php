<?php
require_once __DIR__ . '/html-renderable.php';
require_once __DIR__ . '/html-tag.php';

class RefreshIndicator extends HtmlTag {
    public function __construct($indentation_level) {
        parent::__construct(
           $indentation_level,
           'div',
           array(new HtmlTag($indentation_level + 1, 'div', array(), array('class' => 'spinner'), true)),
           array('id' => 'refresh-indicator')
        );
    }
}