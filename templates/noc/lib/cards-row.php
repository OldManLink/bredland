<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';

class CardsRow extends HtmlTag {
    private $clients;
    private $template_file;

    public function __construct($indentation_level, $clients, $template_file) {
        parent::__construct(
            $indentation_level,
            'div',
            array(),
            array('class' => 'cards-row')
        );

        $this->clients = $clients;
        $this->template_file = $template_file;
    }

    public function render_html($compact) {
        $clients = $this->clients;

        ob_start();
        require $this->template_file;
        $snapshot = ob_get_clean();

        return $this->tag(
            'div',
            array('class' => 'cards-row'),
            array(
                new TextRenderable(
                    $this->child_indentation_level(),
                    $snapshot
                )
            ),
            $compact
        );
    }
}