<?php

abstract class HtmlRenderable {
    const SPACES_PER_LEVEL = 4;
    private $indentation_level;

    private static $void_elements = array(
        '!DOCTYPE html',
        'meta',
        'link'
    );

    public function __construct($indentation_level) {
        $this->indentation_level = $indentation_level;
    }

    public function render($compact=false) {
        return $this->render_html($compact);
    }

    abstract protected function render_html($compact);

    protected function indentation_level($offset = 0) {
        return $this->indentation_level + $offset;
    }

    public function indent() {
        return str_repeat(' ', self::SPACES_PER_LEVEL * $this->indentation_level());
    }

    public function child_indentation_level($offset = 0) {
        return $this->indentation_level(1 + $offset);
    }

    public function tag($name, $attributes, $children, $compact = false) {
        $html = $this->opening_tag($name, $attributes, $compact);
        if ($this->is_void_element($name))
            return $html;
        foreach ($children as $child) {
            $html .= $child->render($compact);
        }
        return $html . $this->closing_tag($name, $compact);
    }

    private function opening_tag($name, $attributes, $compact) {
        $html = $this->indent() . '<' . $name;
        foreach ($attributes as $attribute => $value) {
            $html .= ' ' . $attribute . '="' . $value . '"';
        }
        $html .= ">";
        return $compact ? $html : $html . "\n";
    }

    private function closing_tag($name, $compact) {
        $html = $compact ? '' : $this->indent();
            return $html . '</' . $name . ">\n";
    }

    private function is_void_element($name){
        return in_array($name, self::$void_elements);
    }
}