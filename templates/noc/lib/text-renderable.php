<?php
class TextRenderable extends HtmlRenderable
{
    private $text;

    public function __construct($indentation_level, $text) {
        parent::__construct($indentation_level);
        $this->text = $text;
    }

    protected function render_html($compact) {
        $html = ($compact ? '' : $this->indent()) . $this->text;
        return $compact ? $html : $html . "\n";
    }
}