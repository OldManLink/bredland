<?php
require_once __DIR__ . '/compatibility.php';

function authenticate($host, $token, $host_tokens) {
    if (!array_key_exists($host, $host_tokens)) {
        return false;
    }

    return telemetry_hash_equals($host_tokens[$host], $token);
}