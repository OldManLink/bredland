<?php
require_once __DIR__ . '/html-tag.php';
/**
 * Renders the dashboard body for a cards row.
 *
 * Dashboard is the root of the dashboard UI tree. It owns the composition of
 * the page body while delegating rendering of individual dashboard concepts to
 * specialised renderers as they are introduced.
 */
class Dashboard extends HtmlTag {
    public function __construct($indentation_level, $cards_row) {
        parent::__construct(
            $indentation_level,
            'div',
            array($cards_row),
            array('class' => 'dashboard')
        );
    }
}