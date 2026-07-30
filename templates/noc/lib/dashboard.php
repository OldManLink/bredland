<?php
require_once __DIR__ . '/html-renderable.php';
/**
 * Renders the dashboard body for a collection of clients.
 *
 * Dashboard owns the composition of the dashboard view while delegating
 * rendering of individual dashboard concepts to specialised renderers as
 * they are introduced.
 */
class Dashboard extends HtmlRenderable {
    private $clients;
    private $template_file;

    public function __construct($clients, $template_file) {
        $this->clients = $clients;
        $this->template_file = $template_file;
    }

    public function render_html() {
        $clients = $this->clients;

        ob_start();
        require $this->template_file;
        return ob_get_clean();
    }
}