<?php

class HeartbeatHistoryResponse {
    private $status;
    private $records;

    public function __construct($status, $records) {
        $this->status = $status;
        $this->records = $records;
    }

    public function status() {
        return $this->status;
    }

    public function content_type() {
        return 'application/json; charset=utf-8';
    }

    public function body() {
        return json_encode(
            $this->records,
            JSON_UNESCAPED_SLASHES
        ) . "\n";
    }
}
