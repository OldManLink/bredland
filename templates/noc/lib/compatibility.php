<?php

function telemetry_hash_equals($known, $user) {
    if (function_exists('hash_equals')) {
        return hash_equals($known, $user);
    }

    if (strlen($known) !== strlen($user)) {
        return false;
    }

    $result = 0;

    for ($i = 0; $i < strlen($known); $i++) {
        $result |= ord($known[$i]) ^ ord($user[$i]);
    }

    return $result === 0;
}
