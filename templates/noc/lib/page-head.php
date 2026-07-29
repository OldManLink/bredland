<?php
/**
 * Renders the dashboard head.
 *
 * PageHead owns blablabla.
 */
class PageHead {
    private $template_file;
    private $static_version;

    private static function read_static_version() {
        return trim(file_get_contents(dirname(__DIR__) . '/static/static.version'));
    }

    public function __construct($template_file) {
        $this->template_file = $template_file;
        $this->static_version = self::read_static_version();
    }

    public function render()
    {
        $html = file_get_contents($this->template_file);

        if ($html === false) {
            throw new RuntimeException('failed to read template: ' . $this->template_file);
        }

        return str_replace('__STATIC_VERSION__', $this->static_version, $html);
    }
}