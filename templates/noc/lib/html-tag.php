<?php
require_once __DIR__ . '/html-renderable.php';

class HtmlTag extends HtmlRenderable {
    private $name;
    private $attributes;
    private $children;
    private $compact = false;

    public function __construct(
        $indentation_level,
        $name,
        array $children = array(),
        array $attributes = array(),
        $compact = false
    ) {
        parent::__construct($indentation_level);

        $this->name = $name;
        $this->children = $children;
        $this->attributes = $attributes;
        $this->compact = $compact;
    }

    protected function render_html($compact) {
        return $this->tag(
            $this->name,
            $this->attributes,
            $this->children,
            $this->compact || $compact
        );
    }
}