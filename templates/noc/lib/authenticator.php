<?php

require_once __DIR__ . '/compatibility.php';

class Authenticator {
    private $hostTokens;

    public function __construct($hostTokens) {
        $this->hostTokens = $hostTokens;
    }

    public function authenticate($host, $token) {
        if (!array_key_exists($host, $this->hostTokens)) {
            return false;
        }

        return telemetry_hash_equals(
            $this->hostTokens[$host],
            $token
        );
    }
}
