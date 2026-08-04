<?php
require_once __DIR__ . '/html-renderable.php';
require_once __DIR__ . '/html-tag.php';

class RefreshIndicator extends HtmlTag {
    public function __construct($indentation_level) {
        $child_level = $indentation_level + 1;
        parent::__construct(
           $indentation_level,
           'div',
           array(new HtmlTag($child_level, 'div', array(), array('class' => 'spinner'), true)),
           array('id' => 'refresh-indicator')
        );
    }
}