<?php
require_once __DIR__ . '/html-tag.php';
require_once __DIR__ . '/text-renderable.php';

class CardSlot extends HtmlTag {
    private $client;
    private $template_file;

    public function __construct($indentation_level, $client, $template_file) {
        parent::__construct(
            $indentation_level,
            'div',
            array(),
            array('class' => 'card-slot')
        );

        $this->client = $client;
        $this->template_file = $template_file;
    }

    public function render_html($compact) {
        $client = $this->client;

        ob_start();
        require $this->template_file;
        $snapshot = ob_get_clean();

        return $this->tag(
            'div',
            array('class' => 'card-slot'),
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
