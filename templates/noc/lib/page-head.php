<?php
require_once __DIR__ . '/html-renderable.php';
require_once __DIR__ . '/text-renderable.php';
/**
 * Renders the document head for the dashboard.
 *
 * PageHead owns the metadata and static resource references required by the
 * application. It centralises cache-busted asset URLs and other document-level
 * configuration independently of the dashboard body.
 */
class PageHead extends HtmlRenderable {
    private $static_version;

    private static function read_static_version() {
        return trim(file_get_contents(dirname(__DIR__) . '/static/static.version'));
    }

    public function __construct($indentation_level) {
        parent::__construct($indentation_level);
        $this->static_version = self::read_static_version();
    }

    public function render_html($compact){
        $html = $this->tag('meta', array('charset' => 'utf-8'), array());
        $html .= $this->tag('meta', array('name' => 'viewport', 'content' => 'width=device-width, initial-scale=1'), array());
        $html .= $this->tag('meta', array('name' => 'mobile-web-app-capable', 'content' => 'yes'), array());
        $html .= $this->tag('meta', array('name' => 'theme-color', 'content' => '#ffffff'), array());
        $html .= $this->tag('link', array('rel' => 'manifest', 'href' => 'manifest.json'), array());
        $html .= $this->tag('link', array('rel' => 'apple-touch-icon', 'href' => 'icons/apple-touch-icon.png'), array());
        $html .= $this->tag('link', array('rel' => 'icon', 'type' => 'image/png', 'sizes' => '32x32', 'href' => 'icons/favicon-32x32.png'), array());
        $html .= $this->tag('link', array('rel' => 'icon', 'type' => 'image/png', 'sizes' => '16x16', 'href' => 'icons/favicon-16x16.png'), array());
        $html .= $this->tag('link', array('rel' => 'stylesheet', 'href' => "static/style.css?v=$this->static_version"), array());
        $html .= $this->tag('script', array('src' => "static/dashboard.js?v=$this->static_version"), array(), true);
        $html .= $this->tag('script', array('src' => "static/bootstrap.js?v=$this->static_version", 'defer' => "defer"), array(), true);
        $html .= $this->tag('title', array(), array(new TextRenderable(0, 'Network Operations Centre')), true);

        return $html;
    }
}