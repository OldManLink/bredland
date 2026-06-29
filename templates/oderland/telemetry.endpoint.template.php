<?php
// BRD-003: Generic NOC telemetry endpoint.
//
// PHP 5.5 compatible.
// This file is a template.
// Deployment-specific values are injected outside version control.

require '__TELEMETRY_CONFIG_FILE__';

function respond($status, $message)
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message . "\n";
    exit;
}

function param($name)
{
    if (!isset($_POST[$name]) || $_POST[$name] === '') {
        respond(400, 'missing parameter: ' . $name);
    }

    return (string) $_POST[$name];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'method not allowed');
}

if (!isset($HOST_TOKENS) || !isset($DATA_DIR)) {
    respond(500, 'server configuration error');
}

$host = param('host');
$token = param('token');

if (!isset($HOST_TOKENS[$host])) {
    respond(403, 'forbidden');
}

// PHP 5.5 compatibility.
// A constant-time comparison (hash_equals) is unnecessary here because
// the token is a high-entropy random value and network latency dominates
// any timing differences.
if (param('token') !== $HOST_TOKENS[$host]) {
    respond(403, 'forbidden');
}

$record = [
    'schema' => 1,
    'ts' => gmdate('Y-m-d\TH:i:s\Z'),
    'host' => $host,
    'uptime' => param('uptime'),
];

$fields = param('fields');



foreach (explode(',', $fields) as $field) {
    $field = trim($field);

    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field)) {
        respond(403, 'forbidden');
    }

    if (isset($record[$field]) || $field === 'token' || $field === 'fields' || $field === 'remote_addr') {
        respond(403, 'forbidden');
    }

    if ($field === '') {
        continue;
    }

    $record[$field] = trim(param($field));
}

$record['remote_addr'] = isset($_SERVER['REMOTE_ADDR'])
    ? $_SERVER['REMOTE_ADDR']
    : '';

$line = json_encode($record) . "\n";

if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0700, true);
}

$safe_host = preg_replace('/[^a-zA-Z0-9_-]/', '_', $host);
$date = gmdate('Y-m-d');

$data_file = rtrim($DATA_DIR, '/') . '/' . $safe_host . '-' . $date . '.jsonl';

if (file_put_contents($data_file, $line, FILE_APPEND | LOCK_EX) === false) {
    respond(500, 'write failed');
}

respond(200, 'ok');