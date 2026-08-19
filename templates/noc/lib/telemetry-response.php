<?php

class TelemetryResponse {
    private $status;
    private $body;

    public function __construct($status, $message) {
        $this->status = $status;
        $this->body = $message . "\n";
    }

    public function status() {
        return $this->status;
    }

    public function content_type() {
        return 'text/plain; charset=utf-8';
    }

    public function body() {
        return $this->body;
    }
}
