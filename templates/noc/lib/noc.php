<?php
require_once __DIR__ . '/html-renderable.php';
require_once __DIR__ . '/page-head.php';
require_once __DIR__ . '/refresh-indicator.php';
require_once __DIR__ . '/html-tag.php';
/**
 * Coordinates the construction and rendering of the Network Operations Centre.
 *
 * Noc forms the application boundary for the dashboard. It owns the
 * collaboration between the classes required to load client state and render
 * the page, while delegating each responsibility to specialised objects.
 */
class Noc extends HtmlRenderable {
    private $party_mode;
    private $html;
    /**
     * Returns declarative method contracts only.
     *
     * Maps method names to argument compiler classes.
     * Receivers must not implement compilation logic here.
     */
    public static function compilable_methods() {
        return array(
            'setPartyMode' => BoolVal::class
        );
    }

    public static function now() {
        $now = getenv('NOC_NOW');

        if ($now !== false && $now !== '') {
            return $now;
        }

        return gmdate('c');
    }

    public function __construct($dashboard) {
        parent::__construct(0);

        $head = new HtmlTag(
            $this->child_indentation_level(),
            'head', array(new PageHead($this->child_indentation_level(1))));
        $body = new HtmlTag($this->child_indentation_level(), 'body', array(new RefreshIndicator($this->child_indentation_level(1)), $dashboard));
        $this->html = new HtmlTag(0, 'html', array($head, $body), array('lang' => 'en'));
    }

    public function party_mode() {
        return $this->party_mode;
    }

    public function setPartyMode($party_mode) {
        $this->party_mode = $party_mode;
    }

    public function render_html($compact) {
        return $this->tag('!DOCTYPE html', array(), array()) .
            $this->html->render();
    }
}
