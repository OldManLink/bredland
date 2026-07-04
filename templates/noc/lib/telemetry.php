<?php

function latest_jsonl_line($path) {
    if (!is_readable($path)) {
        return 'unavailable';
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || count($lines) === 0) {
        return 'empty';
    }

    return $lines[count($lines) - 1];
}
?>
