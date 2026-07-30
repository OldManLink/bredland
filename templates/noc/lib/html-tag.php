<?php
require_once __DIR__ . '/html-renderable.php';

class HtmlTag extends HtmlRenderable {
    private $name;
    private $attributes;
    private $children;

    public function __construct(
        $indentation_level,
        $name,
        array $children = array(),
        array $attributes = array()
    ) {
        parent::__construct($indentation_level);

        $this->name = $name;
        $this->children = $children;
        $this->attributes = $attributes;
    }

    protected function render_html() {
        return $this->tag(
            $this->name,
            $this->attributes,
            $this->children
        );
    }
}