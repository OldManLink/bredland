<?php
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

function display_value($value) {
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}
?>
