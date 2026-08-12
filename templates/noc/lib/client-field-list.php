<?php
require_once __DIR__ . '/html-renderable.php';
require_once __DIR__ . '/client-field.php';

class ClientFieldList extends HtmlRenderable {
    private $client_fields;

    public function __construct($indentation_level, $client) {
        parent::__construct($indentation_level);

        $this->client_fields = array();

        foreach ($client->field_list()->fields() as $field) {
            $this->client_fields[] = new ClientField(
                $indentation_level,
                $client,
                $field
            );
        }
    }

    protected function render_html($compact) {
        $html = '';

        foreach ($this->client_fields as $client_field) {
            $html .= $client_field->render($compact);
        }

        return $html;
    }
}
