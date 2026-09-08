<?php

class Notification {
    private $text;
    private $resolution;

    public function __construct($text, $resolution = null) {
        $this->text = $text;
        $this->resolution = $resolution;
    }

    public function text() {
        return $this->text;
    }

    public function resolution() {
        return $this->resolution;
    }

    public function has_resolution() {
        return $this->resolution !== null;
    }
}
