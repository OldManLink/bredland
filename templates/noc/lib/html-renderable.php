<?php

abstract class HtmlRenderable {
    const SPACES_PER_LEVEL = 4;
    private $indentation_level;

    private static $void_elements = array(
        'meta',
        'link'
    );

    public function __construct($indentation_level) {
        $this->indentation_level = $indentation_level;
    }

    public function render() {
        return $this->render_html();
    }

    abstract protected function render_html();

    protected function indentation_level() {
        return $this->indentation_level;
    }

    public function indent() {
        return str_repeat(' ', self::SPACES_PER_LEVEL * $this->indentation_level());
    }

    public function child_indentation_level() {
        return $this->indentation_level() + 1;
    }

    public function tag($name, $attributes, $children) {
        $html = $this->opening_tag($name, $attributes);
        if ($this->is_void_element($name))
            return $html;
        foreach ($children as $child) {
            $html .= $child->render();
        }
        return $html . $this->closing_tag($name);
    }

    private function opening_tag($name, $attributes) {
        $html = $this->indent() . '<' . $name;
        foreach ($attributes as $attribute => $value) {
            $html .= ' ' . $attribute . '="' . $value . '"';
        }
        return $html . ">\n";
    }

    private function closing_tag($name) {
        return $this->indent() . '</' . $name . ">\n";
    }

    private function is_void_element($name){
        return in_array($name, self::$void_elements);
    }
}