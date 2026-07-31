<?php
require_once __DIR__ . '/html-renderable.php';
require_once __DIR__ . '/refresh-indicator.php';
/**
 * Renders the dashboard body for a collection of clients.
 *
 * Dashboard is the root of the dashboard UI tree. It owns the composition of
 * the page body while delegating rendering of individual dashboard concepts to
 * specialised renderers as they are introduced.
 */
class Dashboard extends HtmlRenderable {
    private $clients;
    private $refresh_indicator;
    private $template_file;

    public function __construct($indentation_level, $clients, $template_file) {
        parent::__construct($indentation_level);
        $this->clients = $clients;
        $this->refresh_indicator = new RefreshIndicator($indentation_level + 1);
        $this->template_file = $template_file;
    }

    public function render_html($compact) {
        $clients = $this->clients;

        ob_start();
        echo $this->refresh_indicator->render_html($compact);
        require $this->template_file;
        return ob_get_clean();
    }
}