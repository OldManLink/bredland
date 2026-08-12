<?php

class Notification {
    private $text;

    public function __construct($text) {
        $this->text = $text;
    }

    public function text() {
        return $this->text;
    }
}
