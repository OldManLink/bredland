<?php
date_default_timezone_set('UTC');

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

function heartbeat_from_jsonl($file) {
    return json_decode(latest_jsonl_line($file), true);
}

function heartbeat_age_seconds($heartbeat, $now) {
    if ($heartbeat === null || !isset($heartbeat['ts']) || $now === null) {
        return null;
    }

    $heartbeat_ts = strtotime($heartbeat['ts']);
    $now_ts = strtotime($now);

    if ($heartbeat_ts === false || $now_ts === false) {
        return null;
    }

    return $now_ts - $heartbeat_ts;
}

function heartbeat_health_colour($age_seconds) {
    if ($age_seconds === null) {
        return 'red';
    }

    if ($age_seconds > 1200) {
        return 'red';
    }

    if ($age_seconds > 360) {
        return 'yellow';
    }

    return 'green';
}

function format_duration_seconds($seconds) {
    $seconds = (int)$seconds;

    if ($seconds < 60) {
        return $seconds . 's';
    }

    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;

    if ($minutes < 60) {
        return $minutes . 'm ' . $seconds . 's';
    }

    $hours = floor($minutes / 60);
    $minutes = $minutes % 60;

    if ($hours < 24) {
        return $hours . 'h ' . $minutes . 'm';
    }

    $days = floor($hours / 24);
    $hours = $hours % 24;

    return $days . 'd ' . $hours . 'h';
}

function format_heartbeat_age($age_seconds) {
    if ($age_seconds === null) {
        return 'unavailable';
    }

    return format_duration_seconds($age_seconds) . ' ago';
}

function heartbeat_field($heartbeat, $field, $default) {
    if ($heartbeat === null || !isset($heartbeat[$field])) {
        return $default;
    }

    return $heartbeat[$field];
}

function display_memory($bytes) {
    if ($bytes >= 1073741824) {
        return sprintf('%.1f GiB', $bytes / 1073741824);
    }

    if ($bytes >= 1048576) {
        return sprintf('%.1f MiB', $bytes / 1048576);
    }

    if ($bytes >= 1024) {
        return sprintf('%.1f KiB', $bytes / 1024);
    }

    return $bytes . ' B';
}

function display_uptime($seconds) {
    $seconds = (int)$seconds;

    $weeks = floor($seconds / 604800);
    $seconds = $seconds % 604800;

    $days = floor($seconds / 86400);
    $seconds = $seconds % 86400;

    $hours = floor($seconds / 3600);
    $seconds = $seconds % 3600;

    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;

    if ($weeks > 0) {
        return sprintf('%dw%dd%02d:%02d:%02d', $weeks, $days, $hours, $minutes, $seconds);
    }

    if ($days > 0) {
        return sprintf('%dd%02d:%02d:%02d', $days, $hours, $minutes, $seconds);
    }

    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    if ($minutes > 0) {
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    return sprintf('%ds', $seconds);
}
?>
