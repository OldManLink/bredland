<?php
// BRD-003: Generic NOC telemetry endpoint.
//
// PHP 5.5 compatible.
// This file is a template.
// Deployment-specific values are injected outside version control.

require '__TELEMETRY_CONFIG_FILE__';
require __DIR__ . '/lib/compatibility.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/record.php';
require __DIR__ . '/lib/storage.php';

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

if (!isset($HOST_TOKENS) || !isset($DATA_DIR)) respond(500, 'server configuration error');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, 'method not allowed');

if (!authenticate(param('host'), param('token'), $HOST_TOKENS)) respond(403, 'forbidden');

try {
    $selected_fields = select_fields(param('fields'), $_POST);
} catch (InvalidArgumentException $e) {
    respond(400, $e->getMessage());
}

$record = build_record(
    TELEMETRY_SCHEMA_VERSION,
    gmdate('Y-m-d\TH:i:s\Z'),
    param('host'),
    array_merge(
        array('uptime' => param('uptime')),
        $selected_fields
    )
);

$record['remote_addr'] = isset($_SERVER['REMOTE_ADDR'])
    ? $_SERVER['REMOTE_ADDR']
    : '';

ensure_data_dir($DATA_DIR);

$data_file = daily_jsonl_fileName($DATA_DIR, param('host'), gmdate('Y-m-d'));
append_record($data_file, $record);

respond(200, 'ok');